<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Builds a native vCard 3.0 (RFC 2426, on the RFC 2425 directory profile) — no vendor.
 *
 * - `FN` and `N` are always present; 3.0 requires both. `N` is built from the owner's
 *   structured name attributes when it has any ({@see self::NAME_ATTRIBUTES}); otherwise the
 *   display name fills N's family-name component (`N:Jane Doe;;;;`). `FN` is the owner's
 *   `name`, else the structured parts joined, else "Contact".
 * - A label that names a registered TYPE for its property (`work`, `home`, `cell`, `fax`, …,
 *   case-insensitive; `mobile` → `cell`) is emitted as `TYPE=<token>`. Any other free-text
 *   label rides on a grouped property as `itemN.X-ABLabel` — the de-facto custom-label
 *   property — because parameter values cannot be backslash-escaped text.
 * - TEXT values escape `\`, `,`, `;` and turn every line break (CRLF, CR, LF) into `\n`;
 *   the URL is a URI value and is not text-escaped. Lines fold at 75 octets, never inside a
 *   UTF-8 character.
 */
final class VCardExporter
{
    private const CRLF = "\r\n";

    private const DEFAULT_NAME = 'Contact';

    /** N's components in order, each read from the first of these owner attributes present. */
    private const NAME_ATTRIBUTES = [
        ['last_name', 'family_name', 'surname'],
        ['first_name', 'given_name'],
        ['middle_name', 'additional_name'],
        ['name_prefix', 'honorific_prefix'],
        ['name_suffix', 'honorific_suffix'],
    ];

    /** Registered vCard 3.0 TYPE values per property (RFC 2426 §3.2.1, §3.3.1, §3.3.2). */
    private const TYPES = [
        'ADR' => ['dom', 'intl', 'postal', 'parcel', 'home', 'work', 'pref'],
        'TEL' => ['home', 'msg', 'work', 'pref', 'voice', 'fax', 'cell', 'video', 'pager', 'bbs', 'modem', 'car', 'isdn', 'pcs'],
        'EMAIL' => ['internet', 'x400', 'pref', 'home', 'work'],
    ];

    /** Everyday labels that mean a registered type. */
    private const TYPE_ALIASES = ['mobile' => 'cell'];

    /**
     * Export every contact owned by the given model as a single vCard.
     */
    public static function forOwner(Model $owner): string
    {
        /** @var iterable<Contact> $contacts */
        $contacts = $owner->morphMany(ContactModel::class(), 'owner')->ordered()->get();

        return self::build(self::contactLines($contacts), self::attribute($owner, 'name'), self::structuredName($owner));
    }

    /**
     * @param  iterable<Contact>  $contacts
     */
    public static function forContacts(iterable $contacts, ?string $fullName = null): string
    {
        return self::build(self::contactLines($contacts), $fullName, null);
    }

    /**
     * @param  iterable<Contact>  $contacts
     * @return list<string>
     */
    private static function contactLines(iterable $contacts): array
    {
        $lines = [];
        $group = 0;

        foreach ($contacts as $contact) {
            $value = $contact->value;

            if ($value === null || $value === '') {
                continue;
            }

            [$property, $rendered] = match ($contact->type) {
                ContactType::Email => ['EMAIL', self::text($value)],
                ContactType::Phone => ['TEL', self::text($value)],
                ContactType::Url => ['URL', self::uri($value)],
                ContactType::Address => ['ADR', ';;'.self::text($value).';;;;'],
                default => ['NOTE', self::text($value)],
            };

            $label = $property === 'NOTE' ? '' : trim((string) $contact->label);

            if ($label === '') {
                $lines[] = $property.':'.$rendered;

                continue;
            }

            $type = self::registeredType($property, $label);

            if ($type !== null) {
                $lines[] = $property.';TYPE='.$type.':'.$rendered;

                continue;
            }

            $group++;
            $lines[] = 'item'.$group.'.'.$property.':'.$rendered;
            $lines[] = 'item'.$group.'.X-ABLabel:'.self::text($label);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>|null  $structured  N's five components, when the owner has any
     */
    private static function build(array $lines, ?string $fullName, ?array $structured): string
    {
        $displayName = match (true) {
            $fullName !== null && trim($fullName) !== '' => $fullName,
            $structured !== null => self::joinName($structured),
            default => self::DEFAULT_NAME,
        };

        $components = $structured ?? [$displayName, '', '', '', ''];

        $vcard = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:'.implode(';', array_map(self::text(...), $components)),
            'FN:'.self::text($displayName),
            ...$lines,
            'END:VCARD',
        ];

        return implode(self::CRLF, array_map(self::fold(...), $vcard)).self::CRLF;
    }

    /**
     * N's five components (family, given, additional, prefix, suffix) from the owner's
     * attributes, or null when it has none of them.
     *
     * @return list<string>|null
     */
    private static function structuredName(Model $owner): ?array
    {
        $components = [];

        foreach (self::NAME_ATTRIBUTES as $candidates) {
            $value = null;

            foreach ($candidates as $key) {
                $value ??= self::attribute($owner, $key);
            }

            $components[] = $value ?? '';
        }

        return implode('', $components) === '' ? null : $components;
    }

    /**
     * "Prefix Given Additional Family Suffix", skipping the empty parts.
     *
     * @param  list<string>  $components
     */
    private static function joinName(array $components): string
    {
        [$family, $given, $additional, $prefix, $suffix] = $components + ['', '', '', '', ''];

        return implode(' ', array_filter([$prefix, $given, $additional, $family, $suffix], fn (string $part): bool => $part !== ''));
    }

    /**
     * A non-empty string attribute, read only when the owner actually has it: a host running
     * `Model::shouldBeStrict()` throws on any read of a column its table doesn't carry.
     */
    private static function attribute(Model $owner, string $key): ?string
    {
        if (! array_key_exists($key, $owner->getAttributes())
            && ! $owner->hasGetMutator($key)
            && ! $owner->hasAttributeGetMutator($key)) {
            return null;
        }

        $value = $owner->getAttribute($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function registeredType(string $property, string $label): ?string
    {
        $token = strtolower($label);
        $token = self::TYPE_ALIASES[$token] ?? $token;

        return in_array($token, self::TYPES[$property] ?? [], true) ? $token : null;
    }

    /**
     * Escape a TEXT value (RFC 2426 §4): any line break becomes one `\n`, and `\`, `,`, `;`
     * are backslash-escaped. Other control characters cannot appear in a value and are dropped.
     */
    private static function text(string $value): string
    {
        $value = preg_replace('/\r\n?/', "\n", $value) ?? $value;
        $value = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $value) ?? $value;

        return str_replace(
            ['\\', ',', ';', "\n"],
            ['\\\\', '\,', '\;', '\n'],
            $value,
        );
    }

    /** A URI value is not TEXT: nothing is escaped, only control characters are dropped. */
    private static function uri(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? $value;
    }

    /**
     * Fold a content line longer than 75 octets (RFC 2425 §5.8.1): continuation lines start with
     * a space, and a split never lands inside a multi-byte UTF-8 character.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $current = '';
        $limit = 75;

        foreach (mb_str_split($line, 1, 'UTF-8') as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $chunks[] = $current;
                $current = '';
                // The leading space of a continuation line counts toward its 75 octets.
                $limit = 74;
            }

            $current .= $char;
        }

        $chunks[] = $current;

        return implode(self::CRLF.' ', $chunks);
    }
}
