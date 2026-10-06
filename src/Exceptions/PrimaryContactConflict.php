<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

final class PrimaryContactConflict extends ContactException
{
    public static function requiresOwner(): self
    {
        return new self('A primary contact must belong to an owner.');
    }

    /**
     * A soft-deleted contact holds no primary slot: promoting it would demote the live
     * primary and leave its kind with none.
     */
    public static function trashed(): self
    {
        return new self('A deleted contact cannot be primary.');
    }
}
