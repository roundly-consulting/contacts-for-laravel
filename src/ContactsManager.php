<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Contacts\Actions\AddContactAction;
use RoundlyConsulting\Contacts\Actions\ConfirmContactVerificationAction;
use RoundlyConsulting\Contacts\Actions\DeleteContactAction;
use RoundlyConsulting\Contacts\Actions\RequestContactVerificationAction;
use RoundlyConsulting\Contacts\Actions\SetPrimaryContactAction;
use RoundlyConsulting\Contacts\Actions\SyncContactsAction;
use RoundlyConsulting\Contacts\Actions\UpdateContactAction;
use RoundlyConsulting\Contacts\Actions\VerifyContactAction;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactRules;
use RoundlyConsulting\Contacts\Support\VCardExporter;
use RoundlyConsulting\Contacts\Testing\FakeContactsManager;
use SensitiveParameter;

class ContactsManager
{
    public function __construct(
        private readonly AddContactAction $add,
        private readonly UpdateContactAction $update,
        private readonly DeleteContactAction $delete,
        private readonly SetPrimaryContactAction $setPrimary,
        private readonly VerifyContactAction $verify,
        private readonly SyncContactsAction $sync,
        private readonly RequestContactVerificationAction $requestVerification,
        private readonly ConfirmContactVerificationAction $confirmVerification,
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
     * Generate a verification token for the contact and dispatch
     * ContactVerificationRequested. Returns the plaintext token for delivery.
     */
    public function requestVerification(Contact $contact): string
    {
        return $this->requestVerification->execute($contact);
    }

    public function confirmVerification(Contact $contact, #[SensitiveParameter] string $token): Contact
    {
        return $this->confirmVerification->execute($contact, $token);
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
     * The contacts connected to a given Connectable owner, letting a single
     * shared contact (e.g. a supplier) link to multiple owners without
     * duplication. The owner must use the connections HasConnections trait.
     *
     * @return Collection<int, Model>
     */
    public function sharedWith(Connectable $owner): Collection
    {
        /** @var class-string<Contact> $model */
        $model = config('contacts.model', Contact::class);

        return $owner->connectablesOfType($model);
    }

    /**
     * Validation rules for a repeatable array of contact inputs, suitable for a
     * host FormRequest.
     *
     * @return array<string, list<string|ValidationRule>>
     */
    public function validationRules(string $key = 'contacts'): array
    {
        return ContactRules::forArray($key);
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
