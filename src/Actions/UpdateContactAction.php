<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Events\ContactUpdated;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;

final class UpdateContactAction
{
    public function __construct(
        private readonly SetPrimaryContactAction $setPrimary,
    ) {}

    public function execute(Contact $contact, ContactData $data): Contact
    {
        $data = $data->normalized();

        if (! ValidContactValue::passes($data->type, $data->value, $data->kind)) {
            throw InvalidContactValue::forType($data->type, $data->value, $data->kind);
        }

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

        $contact->save();

        event(new ContactUpdated($contact));

        if ($data->isPrimary && ! $contact->is_primary) {
            $contact = $this->setPrimary->execute($contact);
        }

        return $contact;
    }
}
