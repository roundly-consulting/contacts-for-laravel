<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

use RoundlyConsulting\Contacts\Enums\ContactType;

final class InvalidContactValue extends ContactException
{
    public static function forType(ContactType $type, string $value): self
    {
        return new self(__('contacts::errors.invalid_value', ['type' => $type->value]));
    }
}
