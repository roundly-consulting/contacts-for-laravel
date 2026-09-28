<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Testing;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Assert as PHPUnit;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactAddressFormatter;
use RoundlyConsulting\Contacts\Support\ContactModel;
use SensitiveParameter;

/**
 * Test double for the contacts manager, installed by `Contacts::fake()`. Nothing is
 * written and no event fires: added contacts come back unsaved (a structured address is
 * rendered into the value, never stored), and every add, sync, update, delete, primary
 * change and verification step — through the facade, an injected manager, a contact
 * book, the verification accessor, the HasContacts trait or a Contact model method — is
 * recorded for the assertions below. Reads still hit the database.
 */
final class ContactsFake extends ContactsManager
{
    /** @var list<array{owner: Model, data: ContactData}> */
    private array $added = [];

    /** @var list<array{owner: Model, type: ContactType, items: list<ContactData>}> */
    private array $synced = [];

    /** @var list<array{contact: Contact, data: ContactData}> */
    private array $updated = [];

    /** @var list<Contact> */
    private array $deleted = [];

    /** @var list<Contact> */
    private array $primarySet = [];

    /** @var list<Contact> */
    private array $verified = [];

    /** @var list<Contact> */
    private array $verificationRequested = [];

    /** @var list<Contact> */
    private array $verificationConfirmed = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function addFor(Model $owner, ContactData $data): Contact
    {
        $this->added[] = ['owner' => $owner, 'data' => $data];

        return $this->unsaved($owner, $data);
    }

    /**
     * @param  list<ContactData>  $items
     * @return EloquentCollection<int, Contact>
     */
    public function syncFor(Model $owner, ContactType $type, array $items): EloquentCollection
    {
        $this->synced[] = ['owner' => $owner, 'type' => $type, 'items' => $items];

        return new EloquentCollection(array_map(
            fn (ContactData $item): Contact => $this->unsaved($owner, $item),
            $items,
        ));
    }

    public function update(Contact $contact, ContactData $data): Contact
    {
        $this->updated[] = ['contact' => $contact, 'data' => $data];

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $this->deleted[] = $contact;
    }

    public function setPrimary(Contact $contact): Contact
    {
        $this->primarySet[] = $contact;

        return $contact;
    }

    public function requestVerification(Contact $contact): string
    {
        $this->verificationRequested[] = $contact;

        return 'fake-token';
    }

    public function confirmVerification(Contact $contact, #[SensitiveParameter] string $token): Contact
    {
        $this->verificationConfirmed[] = $contact;

        return $contact;
    }

    public function markVerified(Contact $contact, ?CarbonInterface $at = null): Contact
    {
        $this->verified[] = $contact;

        return $contact;
    }

    /**
     * @param  (Closure(ContactData, Model): bool)|null  $callback
     */
    public function assertAdded(?Closure $callback = null): void
    {
        $matches = array_filter(
            $this->added,
            fn (array $added): bool => $callback === null || $callback($added['data'], $added['owner']) === true,
        );

        PHPUnit::assertNotSame([], $matches, $callback === null ? 'No contact was added.' : 'No matching contact was added.');
    }

    public function assertNothingAdded(): void
    {
        PHPUnit::assertSame([], $this->added, 'A contact was added unexpectedly.');
    }

    /**
     * @param  (Closure(list<ContactData>, Model): bool)|null  $callback
     */
    public function assertSynced(?ContactType $type = null, ?Closure $callback = null): void
    {
        $matches = array_filter(
            $this->synced,
            fn (array $synced): bool => ($type === null || $synced['type'] === $type)
                && ($callback === null || $callback($synced['items'], $synced['owner']) === true),
        );

        PHPUnit::assertNotSame([], $matches, 'No matching contacts were synced.');
    }

    public function assertNothingSynced(): void
    {
        PHPUnit::assertSame([], $this->synced, 'Contacts were synced unexpectedly.');
    }

    /**
     * @param  (Closure(ContactData): bool)|null  $callback
     */
    public function assertUpdated(?Contact $contact = null, ?Closure $callback = null): void
    {
        $matches = array_filter(
            $this->updated,
            fn (array $updated): bool => ($contact === null || $this->same($updated['contact'], $contact))
                && ($callback === null || $callback($updated['data']) === true),
        );

        PHPUnit::assertNotSame([], $matches, 'No matching contact was updated.');
    }

    public function assertNothingUpdated(): void
    {
        PHPUnit::assertSame([], $this->updated, 'A contact was updated unexpectedly.');
    }

    public function assertDeleted(?Contact $contact = null): void
    {
        $this->assertRecorded($this->deleted, $contact, 'No matching contact was deleted.');
    }

    public function assertNothingDeleted(): void
    {
        PHPUnit::assertSame([], $this->deleted, 'A contact was deleted unexpectedly.');
    }

    public function assertPrimarySet(?Contact $contact = null): void
    {
        $this->assertRecorded($this->primarySet, $contact, 'No matching contact was set as primary.');
    }

    public function assertNothingPrimarySet(): void
    {
        PHPUnit::assertSame([], $this->primarySet, 'A primary contact was set unexpectedly.');
    }

    public function assertVerified(?Contact $contact = null): void
    {
        $this->assertRecorded($this->verified, $contact, 'No matching contact was marked verified.');
    }

    public function assertNothingVerified(): void
    {
        PHPUnit::assertSame([], $this->verified, 'A contact was marked verified unexpectedly.');
    }

    public function assertVerificationRequested(?Contact $contact = null): void
    {
        $this->assertRecorded($this->verificationRequested, $contact, 'No matching contact verification was requested.');
    }

    public function assertNoVerificationRequested(): void
    {
        PHPUnit::assertSame([], $this->verificationRequested, 'A contact verification was requested unexpectedly.');
    }

    public function assertVerificationConfirmed(?Contact $contact = null): void
    {
        $this->assertRecorded($this->verificationConfirmed, $contact, 'No matching contact verification was confirmed.');
    }

    public function assertNoVerificationConfirmed(): void
    {
        PHPUnit::assertSame([], $this->verificationConfirmed, 'A contact verification was confirmed unexpectedly.');
    }

    /**
     * @param  list<Contact>  $recorded
     */
    private function assertRecorded(array $recorded, ?Contact $contact, string $message): void
    {
        $matches = array_filter(
            $recorded,
            fn (Contact $candidate): bool => $contact === null || $this->same($candidate, $contact),
        );

        PHPUnit::assertNotSame([], $matches, $message);
    }

    /**
     * An unsaved contact carrying the data, owned by the owner — what the real add would
     * have stored, minus the row. A structured address is rendered, never stored.
     */
    private function unsaved(Model $owner, ContactData $data): Contact
    {
        $value = $data->value;

        if ($data->address instanceof AddressData && trim($value) === '') {
            $value = ContactAddressFormatter::fromData($data->address);
        }

        $model = ContactModel::class();

        return (new $model)->forceFill([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'type' => $data->kind,
            'name' => $data->name ?? '',
            'value' => $data->type->normalize($value),
            'label' => $data->label,
            'category' => $data->category,
            'is_primary' => $data->isPrimary,
            'position' => $data->position ?? 0,
            'meta' => $data->meta,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Unsaved contacts carry no key, so they match by identity; stored ones by key.
     */
    private function same(Contact $a, Contact $b): bool
    {
        return $a === $b || ($a->exists && $b->exists && $a->is($b));
    }
}
