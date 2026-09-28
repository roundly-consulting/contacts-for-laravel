<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Facades;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Contacts\ContactBook;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\ContactVerification;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Testing\ContactsFake;

/**
 * @method static ContactBook for(Model $owner)
 * @method static ContactVerification verification()
 * @method static Contact update(Contact $contact, ContactData $data)
 * @method static void delete(Contact $contact)
 * @method static Contact setPrimary(Contact $contact)
 * @method static Collection<int, Model> sharedWith(Connectable $owner)
 * @method static array<string, list<string|ValidationRule>> validationRules(string $key = 'contacts')
 *
 * @see ContactsManager
 */
final class Contacts extends Facade
{
    /**
     * Swap the manager for a recording fake that writes nothing.
     */
    public static function fake(): ContactsFake
    {
        $fake = new ContactsFake(self::getFacadeApplication() ?? app());

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ContactsManager::class;
    }
}
