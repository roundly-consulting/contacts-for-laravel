<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Actions\AddContactAction;
use RoundlyConsulting\Contacts\Actions\DeleteContactAction;
use RoundlyConsulting\Contacts\Actions\SetPrimaryContactAction;
use RoundlyConsulting\Contacts\Actions\SyncContactsAction;
use RoundlyConsulting\Contacts\Actions\UpdateContactAction;
use RoundlyConsulting\Contacts\Actions\VerifyContactAction;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\VCardExporter;
use RoundlyConsulting\Contacts\Testing\FakeContactsManager;

class ContactsManager
{
    public function __construct(
        private readonly AddContactAction $add,
        private readonly UpdateContactAction $update,
        private readonly DeleteContactAction $delete,
        private readonly SetPrimaryContactAction $setPrimary,
        private readonly VerifyContactAction $verify,
        private readonly SyncContactsAction $sync,
    ) {}

    public function add(Model $owner, ContactData $data): Contact
    {
        return $this->add->execute($owner, $data);
    }

    public function update(Contact $contact, ContactData $data): Contact
    {
        return $this->update->execute($contact, $data);
    }

    public function delete(Contact $contact): void
    {
        $this->delete->execute($contact);
    }

    public function for(Model $owner): PendingContact
    {
        return new PendingContact($this, $owner);
    }

    public function setPrimary(Contact $contact): Contact
    {
        return $this->setPrimary->execute($contact);
    }

    public function verify(Contact $contact, ?CarbonInterface $at = null): Contact
    {
        return $this->verify->execute($contact, $at);
    }

    /**
     * @param  list<ContactData>  $items
     * @return EloquentCollection<int, Contact>
     */
    public function sync(Model $owner, ContactType $type, array $items): EloquentCollection
    {
        return $this->sync->execute($owner, $type, $items);
    }

    public function vCard(Model $owner): string
    {
        return VCardExporter::forOwner($owner);
    }

    /**
     * Swap the bound manager for a recording fake and return it.
     */
    public static function fake(): FakeContactsManager
    {
        $fake = new FakeContactsManager;

        app()->instance(self::class, $fake);

        return $fake;
    }
}
