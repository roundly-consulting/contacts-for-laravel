<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * @phpstan-require-extends Model
 */
trait HasContacts
{
    /**
     * @return MorphMany<Contact, $this>
     */
    public function contacts(): MorphMany
    {
        /** @var class-string<Contact> $model */
        $model = config('contacts.model', Contact::class);

        return $this->morphMany($model, 'owner');
    }
}
