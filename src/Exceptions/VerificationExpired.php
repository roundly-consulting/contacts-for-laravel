<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

final class VerificationExpired extends ContactException
{
    public static function make(): self
    {
        return new self(__('contacts::errors.verification_expired'));
    }
}
