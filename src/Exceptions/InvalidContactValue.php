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
}
