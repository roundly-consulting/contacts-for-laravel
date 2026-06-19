<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Concerns\HasContacts;
use RoundlyConsulting\Contacts\Concerns\RoutesNotificationsViaContacts;

/**
 * @property int $id
 */
final class User extends Model
{
    use HasContacts;
    use RoutesNotificationsViaContacts;

    protected $guarded = [];

    public $timestamps = false;
}
