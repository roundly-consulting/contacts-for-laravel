<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Facades;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\PendingContact;
use RoundlyConsulting\Contacts\Testing\FakeContactsManager;

/**
 * @method static Contact add(Model $owner, ContactData $data)
 * @method static Contact update(Contact $contact, ContactData $data)
 * @method static void delete(Contact $contact)
 * @method static PendingContact for(Model $owner)
 * @method static Contact setPrimary(Contact $contact)
 * @method static Contact verify(Contact $contact, ?CarbonInterface $at = null)
 * @method static string requestVerification(Contact $contact)
 * @method static Contact confirmVerification(Contact $contact, string $token)
 * @method static EloquentCollection<int, Contact> sync(Model $owner, ContactType $type, list<ContactData> $items)
 * @method static string vCard(Model $owner)
 * @method static Collection<int, Model> sharedWith(Connectable $owner)
 * @method static array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule>> validationRules(string $key = 'contacts')
 *
 * @see ContactsManager
 */
final class Contacts extends Facade
{
    public static function fake(): FakeContactsManager
    {
        return ContactsManager::fake();
    }

    protected static function getFacadeAccessor(): string
    {
        return ContactsManager::class;
    }
}
