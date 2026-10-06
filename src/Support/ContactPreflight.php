<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;

/**
 * The checks every contact write runs before it touches the database, in one place so add,
 * update, sync and `Contacts::fake()` agree on what is accepted and what is stored:
 *
 * - a structured address is refused on any kind but address;
 * - a blank value with a structured address becomes the address's one-line render;
 * - the value is normalized for its kind, then validated against the kind's rules.
 *
 * A sync item is first re-kinded to the synced kind and positioned by input order.
 *
 * @internal
 */
final class ContactPreflight
{
    /**
     * The data as it will be stored.
     *
     * @throws InvalidContactValue
     */
    public static function prepare(ContactData $data): ContactData
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

        return $data;
    }

    /**
     * One item of a sync, as it will be stored: re-kinded to the synced kind, positioned by
     * its place in the input, then prepared like any other write.
     *
     * @throws InvalidContactValue
     */
    public static function prepareSyncItem(ContactData $item, string $kind, int $position): ContactData
    {
        return self::prepare(new ContactData(
            type: ContactType::fromValueOrCustom($kind),
            value: $item->value,
            label: $item->label,
            name: $item->name,
            category: $item->category,
            isPrimary: $item->isPrimary,
            position: $position,
            meta: $item->meta,
            kind: $kind,
            address: $item->address,
        ));
    }
}
