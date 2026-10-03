<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Support;

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Support\AddressModel;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Bridges the addresses package into contacts: renders structured address data
 * to a one-line string and attaches an Address to a Contact, keeping the
 * contact's loose `value` in sync with the structured address.
 */
final class ContactAddressFormatter
{
    /**
     * Render AddressData to a single line without persisting anything, reusing
     * the addresses package's own formatter for a consistent shape.
     */
    public static function fromData(AddressData $data): string
    {
        $model = AddressModel::class();

        return (new $model($data->toAttributes()))->formatted();
    }

    /**
     * Attach a structured Address to the contact and mirror its one-line render
     * onto the contact's `value` so existing readers keep working.
     */
    public static function attach(Contact $contact, AddressData $data): Address
    {
        $address = $contact->addAddress($data);

        $formatted = $address->formatted();

        if ($formatted !== '' && $contact->value !== $formatted) {
            $contact->forceFill(['value' => $formatted])->save();
        }

        return $address;
    }
}
