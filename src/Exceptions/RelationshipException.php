<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

/**
 * Raised by the typed relationship helpers (relateTo / relationsOfKind) when a
 * relationship kind is not permitted or an edge would point at the same contact.
 */
final class RelationshipException extends ContactException
{
    public static function unknownKind(string $kind): self
    {
        return new self(sprintf('Unknown relationship kind [%s].', $kind));
    }

    public static function toSelf(): self
    {
        return new self('A contact cannot be related to itself.');
    }
}
