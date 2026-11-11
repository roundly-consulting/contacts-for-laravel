<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Exceptions;

/**
 * Raised by the owner-checked writes on `Contacts::for($owner)` — `update()`, `delete()`
 * and `setPrimary()` — when the contact belongs to another owner (another morph type or
 * another key), to no owner, or the owner has no key yet. Nothing is written and no event
 * fires. The message names neither the contact nor either owner.
 */
final class ContactNotOwned extends ContactException
{
    public static function make(): self
    {
        return new self(__('contacts::errors.not_owned'));
    }
}
