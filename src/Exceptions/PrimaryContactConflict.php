<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

final class PrimaryContactConflict extends ContactException
{
    public static function requiresOwner(): self
    {
        return new self('A primary contact must belong to an owner.');
    }
}
