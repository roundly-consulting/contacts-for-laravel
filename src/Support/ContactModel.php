<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing contacts from `contacts.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that isn't a Contact (so it can't answer the
 * package's queries) falls back to the packaged model.
 */
final class ContactModel
{
    /**
     * @return class-string<Contact>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('contacts.model', Contact::class);

        return is_a($model, Contact::class, true) ? $model : Contact::class;
    }
}
