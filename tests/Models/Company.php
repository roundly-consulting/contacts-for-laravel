<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Contracts\Addressable;
use RoundlyConsulting\Addresses\Traits\HasAddresses;
use RoundlyConsulting\Contacts\Concerns\HasContacts;

/**
 * A host model that uses both HasContacts and HasAddresses — the documented collision
 * workaround: both traits declare `addAddress()`, so the model picks the addresses one and
 * reaches the contacts one as `addAddressContact()`. Without the `insteadof` it is a PHP
 * fatal at class load.
 *
 * @property int $id
 */
final class Company extends Model implements Addressable
{
    use HasAddresses, HasContacts {
        HasAddresses::addAddress insteadof HasContacts;
    }

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
