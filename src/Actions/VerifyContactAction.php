<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Contacts\Events\ContactVerified;
use RoundlyConsulting\Contacts\Models\Contact;

final readonly class VerifyContactAction
{
    public function execute(Contact $contact, ?CarbonInterface $at = null): Contact
    {
        if ($contact->verified_at !== null) {
            return $contact;
        }

        $contact->verified_at = $at ?? Carbon::now();
        $contact->save();

        event(new ContactVerified($contact));

        return $contact;
    }
}
