<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

/**
 * The wrong guess that spent the token's last attempt (`contacts.verification.max_attempts`).
 * The token is void from here on; the contact needs a new one.
 */
final class VerificationAttemptsExceeded extends InvalidVerificationToken
{
    public static function make(): self
    {
        return new self(__('contacts::errors.verification_attempts_exceeded'));
    }
}
