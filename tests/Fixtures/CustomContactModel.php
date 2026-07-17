<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Tests\Fixtures;

use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * The host subclass `contacts.model` invites, used to prove the seam is real.
 *
 * `CountsCreations` is what makes the proof independent of `instanceof`: it counts rows
 * created as *this exact class*, so a contact row created as the packaged Contact —
 * which would still satisfy `instanceof` while firing none of the host's model events
 * (permissions #31) — cannot be mistaken for an honoured swap.
 *
 * Distinct from `Tests\Models\CustomContact`, which is `final` and exists only to pin
 * that `Contact` stays extendable. This one has to be non-final-friendly and observable.
 */
class CustomContactModel extends Contact
{
    use CountsCreations;

    protected $table = 'contacts';
}
