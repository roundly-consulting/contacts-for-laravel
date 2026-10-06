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
}
