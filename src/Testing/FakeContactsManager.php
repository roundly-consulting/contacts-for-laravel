<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Testing;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;

/**
 * Recording stand-in for ContactsManager used by host-app tests. It records
 * every call instead of touching real actions, events, or the database.
 */
final class FakeContactsManager extends ContactsManager
{
    /** @var list<array{owner: Model, data: ContactData}> */
    public array $added = [];

    /** @var list<array{contact: Contact, data: ContactData}> */
    public array $updated = [];

    /** @var list<Contact> */
    public array $deleted = [];

    /** @var list<Contact> */
    public array $primarySet = [];

    /** @var list<Contact> */
    public array $verified = [];

    /** @var list<array{owner: Model, type: ContactType, items: list<ContactData>}> */
    public array $synced = [];

    public function __construct()
    {
        // Intentionally bypass the parent constructor: the fake records calls
        // and never delegates to the real action dependencies.
    }

    public function add(Model $owner, ContactData $data): Contact
    {
        $this->added[] = ['owner' => $owner, 'data' => $data];

        return new Contact;
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

    public function verify(Contact $contact, ?CarbonInterface $at = null): Contact
    {
        $this->verified[] = $contact;

        return $contact;
    }

    /**
     * @param  list<ContactData>  $items
     * @return EloquentCollection<int, Contact>
     */
    public function sync(Model $owner, ContactType $type, array $items): EloquentCollection
    {
        $this->synced[] = ['owner' => $owner, 'type' => $type, 'items' => $items];

        /** @var EloquentCollection<int, Contact> $collection */
        $collection = new EloquentCollection;

        return $collection;
    }

    /**
     * @param  (Closure(ContactData, Model): bool)|null  $callback
     */
    public function assertAdded(?Closure $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->added, 'No contact was added.');

            return;
        }

        $matched = array_filter(
            $this->added,
            fn (array $call): bool => $callback($call['data'], $call['owner']),
        );

        Assert::assertNotEmpty($matched, 'No matching contact was added.');
    }

    public function assertVerified(?Contact $contact = null): void
    {
        if ($contact === null) {
            Assert::assertNotEmpty($this->verified, 'No contact was verified.');

            return;
        }

        $matched = array_filter(
            $this->verified,
            fn (Contact $verified): bool => $verified->is($contact),
        );

        Assert::assertNotEmpty($matched, 'The given contact was not verified.');
    }

    public function assertPrimarySet(?Contact $contact = null): void
    {
        if ($contact === null) {
            Assert::assertNotEmpty($this->primarySet, 'No primary contact was set.');

            return;
        }

        $matched = array_filter(
            $this->primarySet,
            fn (Contact $primary): bool => $primary->is($contact),
        );

        Assert::assertNotEmpty($matched, 'The given contact was not set as primary.');
    }

    public function assertNothingAdded(): void
    {
        Assert::assertEmpty($this->added, 'A contact was added unexpectedly.');
    }
}
