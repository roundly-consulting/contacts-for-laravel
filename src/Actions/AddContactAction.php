<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Events\ContactAdded;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;

final class AddContactAction
{
    public function __construct(
        private readonly SetPrimaryContactAction $setPrimary,
    ) {}

    public function execute(Model $owner, ContactData $data): Contact
    {
        $data = $data->normalized();

        if (! ValidContactValue::passes($data->type, $data->value)) {
            throw InvalidContactValue::forType($data->type, $data->value);
        }

        /** @var class-string<Contact> $model */
        $model = config('contacts.model', Contact::class);

        $isFirstOfKind = ! $owner->morphMany($model, 'owner')
            ->where('type', $data->type->value)
            ->exists();

        $shouldBePrimary = $data->isPrimary
            || ($isFirstOfKind && (bool) config('contacts.auto_primary', true));

        $position = $data->position ?? $this->nextPosition($owner, $model, $data->type->value);

        /** @var Contact $contact */
        $contact = $owner->morphMany($model, 'owner')->create([
            'type' => $data->type,
            'name' => $data->name ?? '',
            'value' => $data->value,
            'label' => $data->label,
            'category' => $data->category,
            'position' => $position,
            'is_primary' => false,
            'meta' => $data->meta,
        ]);

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
