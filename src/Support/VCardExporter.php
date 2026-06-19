<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Builds a minimal, native vCard 3.0 string from a set of contacts — no vendor.
 */
final class VCardExporter
{
    /**
     * Export every contact owned by the given model as a single vCard.
     */
    public static function forOwner(Model $owner): string
    {
        /** @var iterable<Contact> $contacts */
        $contacts = $owner->morphMany(Contact::class, 'owner')->ordered()->get();

        $fullName = self::ownerName($owner);

        return self::build(self::contactLines($contacts), $fullName);
    }

    /**
     * @param  iterable<Contact>  $contacts
     */
    public static function forContacts(iterable $contacts, ?string $fullName = null): string
    {
        return self::build(self::contactLines($contacts), $fullName);
    }

    /**
     * @param  iterable<Contact>  $contacts
     * @return list<string>
     */
    private static function contactLines(iterable $contacts): array
    {
        $lines = [];

        foreach ($contacts as $contact) {
            $value = $contact->value;

            if ($value === null || $value === '') {
                continue;
            }

            $type = $contact->type;
            $label = self::escape($contact->label ?? '');
            $typeParam = $label !== '' ? ';TYPE='.$label : '';

            $lines[] = match ($type) {
                ContactType::Email => 'EMAIL'.$typeParam.':'.self::escape($value),
                ContactType::Phone => 'TEL'.$typeParam.':'.self::escape($value),
                ContactType::Url => 'URL'.$typeParam.':'.self::escape($value),
                ContactType::Address => 'ADR'.$typeParam.':;;'.self::escape($value).';;;;',
                default => 'NOTE:'.self::escape($value),
            };
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function build(array $lines, ?string $fullName): string
    {
        $name = $fullName !== null && $fullName !== '' ? $fullName : 'Contact';

        $vcard = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:'.self::escape($name),
            ...$lines,
            'END:VCARD',
        ];

        return implode("\r\n", $vcard)."\r\n";
    }

    private static function ownerName(Model $owner): ?string
    {
        $name = $owner->getAttribute('name');

        return is_string($name) ? $name : null;
    }

    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', ',', ';', "\n"],
            ['\\\\', '\,', '\;', '\n'],
            $value,
        );
    }
}
