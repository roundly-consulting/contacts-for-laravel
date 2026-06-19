# Contacts for Laravel

Store and query contacts for any entity or category in a Laravel application. Use it as a
standalone contact book, or attach contacts to any model (a user, a company, an order, …)
through a polymorphic relationship.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`

## Installation

```bash
composer require roundly-consulting/contacts-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="contacts-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="contacts-config"
```

## Configuration

The published `config/contacts.php` exposes a single key:

```php
<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Models\Contact;

return [

    // The Eloquent model used to store contacts. Swap it for your own model
    // (extending the package's Contact) if you need custom behaviour.
    'model' => Contact::class,

];
```

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `model` | `class-string` | `RoundlyConsulting\Contacts\Models\Contact::class` | The model the `HasContacts` trait resolves when building the `contacts()` relationship. |

## Usage

### Standalone contacts

```php
use RoundlyConsulting\Contacts\Models\Contact;

$contact = Contact::create([
    'name' => 'Plumber',
    'value' => '+421 900 000 000',
    'category' => 'Utility',
    'meta' => collect(['emergency' => true, 'rating' => 8.0]),
]);
```

The `meta` column is cast to an `Illuminate\Support\Collection`, so you can store arbitrary
structured data alongside each contact.

### Attaching contacts to an entity

Add the `HasContacts` trait to any Eloquent model that should own contacts:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Concerns\HasContacts;

class User extends Model
{
    use HasContacts;
}
```

Then create and read contacts through the relationship:

```php
$user->contacts()->create([
    'name' => 'Direct line',
    'value' => '+421 900 111 222',
    'category' => 'Work',
]);

$user->contacts; // Illuminate\Database\Eloquent\Collection of Contact models
```

### Query scopes

The `Contact` model ships four scopes:

```php
use RoundlyConsulting\Contacts\Models\Contact;

// Contacts owned by a specific entity.
Contact::query()->forOwner($user)->get();

// Contacts with no owner (global contacts).
Contact::query()->withoutOwner()->get();

// Owned contacts plus global contacts in one query.
Contact::query()->forOwner(owner: $user, includeAlsoWithoutOwner: true)->get();

// Filter by category.
Contact::query()->forCategory('Utility')->get();
Contact::query()->withoutCategory()->get();

// Scopes compose — e.g. a user's "Family" contacts.
Contact::query()->forOwner($user)->forCategory('Family')->get();

// Also available on the owner relationship.
$user->contacts()->forCategory('Family')->get();
```

Contacts use soft deletes, so deleting a contact keeps it retrievable via `withTrashed()`.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
