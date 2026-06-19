<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Concerns\HasContacts;

/**
 * @property int $id
 */
final class User extends Model
{
    use HasContacts;

    protected $guarded = [];

    public $timestamps = false;
}
