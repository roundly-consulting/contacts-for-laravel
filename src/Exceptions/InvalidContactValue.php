<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

use RoundlyConsulting\Contacts\Enums\ContactType;

final class InvalidContactValue extends ContactException
{
    /**
     * @param  string|null  $kind  the raw kind, when it differs from the type — a custom
     *                             kind names itself in the message rather than reporting
     *                             the useless "custom".
     */
    public static function forType(ContactType $type, string $value, ?string $kind = null): self
    {
        return new self(__('contacts::errors.invalid_value', ['type' => $kind ?? $type->value]));
    }

    /**
     * A structured postal address was given for a contact that is not an address — its
     * one-line render would otherwise overwrite (and bypass validation of) the real value.
     */
    public static function structuredAddressOn(string $kind): self
    {
        return new self(__('contacts::errors.structured_address_kind', ['type' => $kind]));
    }
}
