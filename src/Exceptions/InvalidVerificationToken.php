<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

final class InvalidVerificationToken extends ContactException
{
    public static function make(): self
    {
        return new self(__('contacts::errors.invalid_verification_token'));
    }
}
