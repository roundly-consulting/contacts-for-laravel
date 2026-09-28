<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

/**
 * The token is wrong, was never issued, or is no longer the contact's current one.
 *
 * Not final: VerificationAttemptsExceeded refines it, so a host that catches this one
 * also catches the guess that voided the token.
 */
class InvalidVerificationToken extends ContactException
{
    public static function make(): self
    {
        return new self(__('contacts::errors.invalid_verification_token'));
    }
}
