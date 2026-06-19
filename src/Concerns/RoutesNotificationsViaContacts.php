<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Opt-in notification routing that resolves channel destinations from an
 * owner's primary contacts instead of model attributes.
 *
 * This trait is deliberately separate from {@see HasContacts}: host models such
 * as a User commonly already use Laravel's Notifiable trait, and silently
 * overriding its attribute-based routing would be surprising. Apply this trait
 * only on models that should route notifications through their contacts.
 *
 * Precedence: when applied, these methods take precedence over Notifiable's
 * attribute-based routing for the mail, Vonage, and Twilio channels — Laravel's
 * notification router calls routeNotificationFor<Channel>() when it exists. Each
 * method gracefully returns null when the owner has no primary contact of that
 * kind, in which case Laravel skips delivery on that channel.
 *
 * @phpstan-require-extends Model
 */
trait RoutesNotificationsViaContacts
{
    public function routeNotificationForMail(): ?string
    {
        return $this->primaryContactValueFor(ContactType::Email);
    }

    public function routeNotificationForVonage(): ?string
    {
        return $this->primaryContactValueFor(ContactType::Phone);
    }

    public function routeNotificationForTwilio(): ?string
    {
        return $this->primaryContactValueFor(ContactType::Phone);
    }

    private function primaryContactValueFor(ContactType $type): ?string
    {
        /** @var class-string<Contact> $model */
        $model = config('contacts.model', Contact::class);

        $contact = $model::query()
            ->forOwner($this)
            ->ofType($type)
            ->primary()
            ->ordered()
            ->first();

        $value = $contact?->value;

        return ($value === null || $value === '') ? null : $value;
    }
}
