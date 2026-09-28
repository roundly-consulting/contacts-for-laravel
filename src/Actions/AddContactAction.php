<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Events\ContactAdded;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;
use RoundlyConsulting\Contacts\Support\ContactAddressFormatter;
use RoundlyConsulting\Contacts\Support\ContactModel;

/**
 * Add a normalized, validated contact to an owner. The first contact of a kind becomes
 * primary (unless `contacts.auto_primary` is off); a structured address on the data is
 * attached in the same transaction and its one-line render becomes the contact's value.
 */
final readonly class AddContactAction
{
    public function __construct(
        private SetPrimaryContactAction $setPrimary,
    ) {}

    public function execute(Model $owner, ContactData $data): Contact
    {
        if ($data->address instanceof AddressData) {
            if ($data->type !== ContactType::Address) {
                throw InvalidContactValue::structuredAddressOn($data->kind);
            }

            if (trim($data->value) === '') {
                $data = $data->withValue(ContactAddressFormatter::fromData($data->address));
            }
        }

        $data = $data->normalized();

        if (! ValidContactValue::passes($data->type, $data->value, $data->kind)) {
            throw InvalidContactValue::forType($data->type, $data->value, $data->kind);
        }

        $model = ContactModel::class();

        // Scoped by the RAW kind, so "first of kind" and the primary-per-kind guarantee
        // treat a registered custom kind (e.g. whatsapp) as its own kind rather than
        // lumping every custom contact together under `custom`.
        $isFirstOfKind = ! $owner->morphMany($model, 'owner')
            ->where('type', $data->kind)
            ->exists();

        $shouldBePrimary = $data->isPrimary
            || ($isFirstOfKind && (bool) config('contacts.auto_primary', true));

        $position = $data->position ?? $this->nextPosition($owner, $model, $data->kind);

        $contact = DB::transaction(function () use ($owner, $model, $data, $position): Contact {
            /** @var Contact $contact */
            $contact = $owner->morphMany($model, 'owner')->create([
                'type' => $data->kind,
                'name' => $data->name ?? '',
                'value' => $data->value,
                'label' => $data->label,
                'category' => $data->category,
                'position' => $position,
                'is_primary' => false,
                'meta' => $data->meta,
            ]);

            if ($data->address instanceof AddressData) {
                ContactAddressFormatter::attach($contact, $data->address);
            }

            return $contact;
        });

        event(new ContactAdded($contact));

        if ($shouldBePrimary) {
            $contact = $this->setPrimary->execute($contact);
        }

        return $contact;
    }

    /**
     * @param  class-string<Contact>  $model
     */
    private function nextPosition(Model $owner, string $model, string $type): int
    {
        $max = $owner->morphMany($model, 'owner')
            ->where('type', $type)
            ->max('position');

        return is_numeric($max) ? ((int) $max) + 1 : 0;
    }
}
