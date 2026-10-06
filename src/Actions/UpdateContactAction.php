<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Events\ContactUpdated;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactAddressFormatter;
use RoundlyConsulting\Contacts\Support\ContactPreflight;
use RoundlyConsulting\Contacts\Support\KindGroup;
use Throwable;

/**
 * Overwrite a contact with new, normalized data. A changed value or kind drops the
 * verification (see Contact::booted()).
 *
 * The data is checked exactly as `add()` checks it: a structured address is refused on any
 * kind but address, and a blank value with one becomes its render. A structured address
 * replaces the contact's own (in the same transaction as the row) and its render becomes the
 * value; a value-only change keeps the structured address and only rewords the text.
 *
 * A kind change keeps one primary per kind: a primary moving into a kind that already has
 * one is demoted (unless `isPrimary` asks for it, which demotes the other instead), and —
 * while `contacts.auto_primary` is on — the kind it left promotes its next contact, and a
 * kind it enters without a primary gets one.
 */
final readonly class UpdateContactAction
{
    public function __construct(
        private SetPrimaryContactAction $setPrimary,
        private PromoteNextPrimaryContactAction $promoteNext,
    ) {}

    /**
     * @throws InvalidContactValue
     */
    public function execute(Contact $contact, ContactData $data): Contact
    {
        $data = ContactPreflight::prepare($data);

        $previousKind = $contact->kind;
        $wasPrimary = $contact->is_primary;

        // The value the stored structured address renders, read before it is overwritten.
        $mirrored = $contact->getRawOriginal('value');

        $attributes = $contact->getAttributes();
        $original = $contact->getRawOriginal();

        try {
            $kindChanged = $contact->getConnection()->transaction(
                fn (): bool => $this->write($contact, $data, $previousKind, $wasPrimary, is_string($mirrored) ? $mirrored : null),
            );
        } catch (Throwable $exception) {
            // Nothing was stored, so the caller's copy must not look as if it was.
            $contact->setRawAttributes($original, true)->setRawAttributes($attributes);

            throw $exception;
        }

        event(new ContactUpdated($contact));

        if ($data->isPrimary && ! $contact->is_primary) {
            $contact = $this->setPrimary->execute($contact);
        }

        if ($kindChanged) {
            if ($wasPrimary) {
                $this->promoteNext->execute($contact, $previousKind);
            }

            if (! $contact->is_primary && $this->promoteNext->execute($contact, $contact->kind)?->is($contact) === true) {
                $contact->refresh();
            }
        }

        return $contact;
    }

    /**
     * The contact row and its structured address, written together.
     *
     * @return bool whether the kind changed
     */
    private function write(Contact $contact, ContactData $data, string $previousKind, bool $wasPrimary, ?string $mirrored): bool
    {
        $contact->fill([
            'type' => $data->kind,
            'value' => $data->value,
            'label' => $data->label,
            'category' => $data->category,
            'meta' => $data->meta,
        ]);

        if ($data->name !== null) {
            $contact->name = $data->name;
        }

        if ($data->position !== null) {
            $contact->position = $data->position;
        }

        $kindChanged = $contact->kind !== $previousKind;

        if ($kindChanged && $wasPrimary && KindGroup::hasOtherPrimary($contact)) {
            $contact->is_primary = false;
        }

        $contact->save();

        if ($data->address instanceof AddressData) {
            ContactAddressFormatter::replace($contact, $data->address, $mirrored);
        }

        return $kindChanged;
    }
}
