<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Container\Container;
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
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\Contacts\Support\ContactRules;
use SensitiveParameter;

/**
 * The root of the `Contacts` facade: an owner's contact book (`for()`), the
 * verification flow (`verification()`), and the operations on a single contact that
 * need no owner scope.
 *
 * Deliberately not `final`: `Testing\ContactsFake` extends it, so a constructor-injected
 * manager keeps working under `Contacts::fake()`.
 */
class ContactsManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * The contact book of one owner: add, sync, read and export its contacts.
     */
    public function for(Model $owner): ContactBook
    {
        return new ContactBook($this, $owner);
    }

    /**
     * The verification flow: issue a token, confirm it, or mark a contact verified.
     */
    public function verification(): ContactVerification
    {
        return new ContactVerification($this);
    }

    /**
     * Overwrite a contact with new data; a primary flag promotes it within its kind.
     */
    public function update(Contact $contact, ContactData $data): Contact
    {
        return $this->container->make(UpdateContactAction::class)->execute($contact, $data);
    }

    /**
     * Soft-delete a contact (dispatches ContactDeleted).
     */
    public function delete(Contact $contact): void
    {
        $this->container->make(DeleteContactAction::class)->execute($contact);
    }

    /**
     * Promote a contact to primary, demoting its owner's other primary of the same kind.
     */
    public function setPrimary(Contact $contact): Contact
    {
        return $this->container->make(SetPrimaryContactAction::class)->execute($contact);
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
        return $owner->connectablesOfType(ContactModel::class());
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

    /*
     * The operations behind for() and verification(). They are public only so the handles
     * can reach them, and @internal so the facade never documents them. They are also the
     * ONE place ContactsFake overrides: every call — through the facade, an injected
     * manager, a handle, the HasContacts trait or a Contact model method — lands here.
     */

    /**
     * @internal the body of `for($owner)->add()` and the fluent `->email(…)->add()`
     */
    public function addFor(Model $owner, ContactData $data): Contact
    {
        return $this->container->make(AddContactAction::class)->execute($owner, $data);
    }

    /**
     * @internal the body of `for($owner)->sync()`
     *
     * @param  list<ContactData>  $items
     * @return EloquentCollection<int, Contact>
     */
    public function syncFor(Model $owner, ContactType $type, array $items): EloquentCollection
    {
        return $this->container->make(SyncContactsAction::class)->execute($owner, $type, $items);
    }

    /**
     * @internal the body of `verification()->request()`
     */
    public function requestVerification(Contact $contact): string
    {
        return $this->container->make(RequestContactVerificationAction::class)->execute($contact);
    }

    /**
     * @internal the body of `verification()->confirm()`
     */
    public function confirmVerification(Contact $contact, #[SensitiveParameter] string $token): Contact
    {
        return $this->container->make(ConfirmContactVerificationAction::class)->execute($contact, $token);
    }

    /**
     * @internal the body of `verification()->markVerified()`
     */
    public function markVerified(Contact $contact, ?CarbonInterface $at = null): Contact
    {
        return $this->container->make(VerifyContactAction::class)->execute($contact, $at);
    }
}
