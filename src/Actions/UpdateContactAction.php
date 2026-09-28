<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Events\ContactUpdated;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;
use RoundlyConsulting\Contacts\Support\KindGroup;

/**
 * Overwrite a contact with new, normalized data. A changed value or kind drops the
 * verification (see Contact::booted()).
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

    public function execute(Contact $contact, ContactData $data): Contact
    {
        $data = $data->normalized();

        if (! ValidContactValue::passes($data->type, $data->value, $data->kind)) {
            throw InvalidContactValue::forType($data->type, $data->value, $data->kind);
        }

        $previousKind = $contact->kind;
        $wasPrimary = $contact->is_primary;

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
}
