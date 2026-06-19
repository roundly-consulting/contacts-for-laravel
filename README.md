# Contacts for Laravel

Typed, validated, primary-aware contacts for any Laravel model. Attach emails, phones,
addresses, URLs, social handles, and custom kinds to a user, company, order, or anything
else — with normalization, a primary-per-kind guarantee, ordering, verification, events,
a fluent facade, and native vCard export. Zero non-Laravel runtime dependencies.

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

Optionally publish the config file or translations:

```bash
php artisan vendor:publish --tag="contacts-config"
php artisan vendor:publish --tag="contacts-translations"
```

## Configuration

The published `config/contacts.php`:

```php
return [
    'model' => RoundlyConsulting\Contacts\Models\Contact::class,
    'table' => 'contacts',
    'auto_primary' => true,
    'require_owner_for_primary' => false,
    'default_country_code' => env('CONTACTS_DEFAULT_COUNTRY_CODE'),
    'types' => [
        // 'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'chat', 'rules' => ['required', 'string']],
    ],
];
```

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `model` | `class-string` | `Contact::class` | Model the `HasContacts` trait resolves for the `contacts()` relationship. |
| `table` | `string` | `contacts` | Database table contacts are stored in. |
| `auto_primary` | `bool` | `true` | When set, the first contact of a kind added for an owner becomes its primary. |
| `require_owner_for_primary` | `bool` | `false` | When set, only owned contacts may be primary; otherwise a `PrimaryContactConflict` is thrown. |
| `default_country_code` | `?string` | `env('CONTACTS_DEFAULT_COUNTRY_CODE')` | Best-effort dialling prefix added to phone numbers entered without a leading `+`. |
| `types` | `array` | `[]` | Register custom kinds and override the label/icon/rules of built-in kinds. |

## Contact kinds

`RoundlyConsulting\Contacts\Enums\ContactType` is a backed string enum with six kinds:
`Email`, `Phone`, `Address`, `Url`, `Social`, `Custom`. Each kind drives validation,
normalization, a translatable label, and an icon name:

- **Email** — lower-cased and trimmed, validated with the `email` rule.
- **Phone** — separators stripped, leading `+` kept, validated against an E.164-ish regex.
  Without a `+`, `default_country_code` is prepended when configured.
- **Url** — `https://` prepended when no scheme is present, validated with the `url` rule.
- **Address / Social / Custom** — trimmed; `required|string` by default.

Any stored type outside the six built-ins (e.g. a `whatsapp` kind registered in config) is
treated as `Custom` and takes its label/icon/rules from `config('contacts.types.<key>')`.

## Usage

### Trait sugar (the common case)

Add the `HasContacts` trait to any model:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Concerns\HasContacts;

class User extends Model
{
    use HasContacts;
}
```

```php
// Add — normalized + validated, primary guaranteed unique per kind.
$user->addEmail('A.Person@Example.COM ', label: 'Work', primary: true);
$user->addPhone('+421 900 000 000', label: 'Mobile');
$user->addUrl('example.com');
$user->addAddress('12 Main St');

$user->primaryEmail()?->value;          // 'a.person@example.com'
$user->primaryPhone()?->value;          // '+421900000000'
$user->contactsOfType('phone');         // ordered EloquentCollection
```

### The `Contacts` facade and fluent builder

```php
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;

Contacts::for($user)
    ->phone('+421 900 000 000')
    ->label('Mobile')
    ->primary()
    ->add();

Contacts::add($user, new ContactData(ContactType::Email, 'a@b.com'));
Contacts::setPrimary($contact);
Contacts::verify($contact);
Contacts::delete($contact);
```

### Syncing a set of contacts (e.g. a profile form)

```php
Contacts::sync($user, ContactType::Phone, [
    new ContactData(ContactType::Phone, '+421900000000', label: 'Mobile', isPrimary: true),
    new ContactData(ContactType::Phone, '+421900111222', label: 'Office'),
]);
```

`sync()` updates matching values in place, creates new ones, deletes the rest, and assigns
`position` by input order.

### Reusing validation in a FormRequest

```php
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;

$request->validate([
    'email' => ['required', new ValidContactValue(ContactType::Email)],
]);
```

### Query scopes

```php
use RoundlyConsulting\Contacts\Models\Contact;

Contact::query()->forOwner($user)->get();
Contact::query()->forOwner(owner: $user, includeAlsoWithoutOwner: true)->get();
Contact::query()->withoutOwner()->get();
Contact::query()->forCategory('Utility')->get();
Contact::query()->withoutCategory()->get();

Contact::query()->ofType(ContactType::Email)->get();   // or ->ofType('email')
Contact::query()->primary()->get();
Contact::query()->verified()->get();                   // ->verified(false) for unverified
Contact::query()->search('alice')->get();              // name / value / label LIKE
Contact::query()->ordered()->get();                    // by position, then id
```

### Events

Each write dispatches an event so host apps can react (send a verification email, sync a
CRM, …) without forking:

`ContactAdded`, `ContactUpdated`, `ContactDeleted`, `ContactVerified`, `PrimaryContactChanged`
— each carries the affected `Contact` (and the previous primary, where relevant).

### vCard export

```php
Contacts::vCard($user);   // a vCard 3.0 string for the owner's contacts
$contact->toVCard();      // a single contact
```

### Testing helper

```php
use RoundlyConsulting\Contacts\Facades\Contacts;

$fake = Contacts::fake();

$user->addEmail('a@b.com');

$fake->assertAdded(fn ($data) => $data->value === 'a@b.com');
$fake->assertVerified();
$fake->assertPrimarySet();
```

Model factories ship states for ergonomic test setup: `email()`, `phone()`, `url()`,
`address()`, `primary()`, `verified()`, `forOwner($model)`, and `ofType($type)`.

### Backward compatibility

The existing low-level API is unchanged: `contacts()`, the original scopes, the `meta`
collection cast, and direct `$user->contacts()->create([...])` all still work. Normalization,
validation, and auto-primary apply only through the action / facade / trait-sugar path.

Contacts use soft deletes, so a deleted contact stays retrievable via `withTrashed()`.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
