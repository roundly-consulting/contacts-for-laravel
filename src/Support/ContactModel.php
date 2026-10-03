<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing contacts from `contacts.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class ContactModel
{
    /**
     * @return class-string<Contact>
     */
    public static function class(): string
    {
        return ModelResolver::for('contacts.model', Contact::class);
    }
}
