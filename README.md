<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/contacts-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=contacts-for-laravel">
    <img src="art/hero.png" alt="Contacts for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/contacts-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/contacts-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/contacts-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/contacts-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/contacts-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/contacts-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=contacts-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Contacts for Laravel

Typed, validated, primary-aware contacts for any Laravel model. Attach emails, phones,
addresses, URLs, social handles, and custom kinds to a user, company, order, or anything
else — with normalization, a primary-per-kind guarantee, ordering, verification, events,
a fluent facade, and native vCard export. At runtime it needs only Laravel and five sibling
Roundly packages (see [Integrates with](#integrates-with)).

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`

## Installation

```bash
composer require roundly-consulting/contacts-for-laravel
```

The migration is **publish-only** — the package never runs it for you. Publish it into your app's
`database/migrations`, then migrate:

```bash
php artisan vendor:publish --tag="contacts-migrations"
php artisan migrate
```

The migration lands as a timestamped file you own. Re-publishing an unedited copy (with `--force`)
overwrites it in place instead of adding a second one; a copy you edited — or any other
same-named migration — is never overwritten: the package's migration is published beside it under
a fresh timestamp, and you decide which one to keep.

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
    'key_type' => env('CONTACTS_KEY_TYPE', 'bigint'),
    'auto_primary' => true,
    'require_owner_for_primary' => false,
    'default_country_code' => env('CONTACTS_DEFAULT_COUNTRY_CODE'),
    'verification' => [
        'ttl' => env('CONTACTS_VERIFICATION_TTL', 60),
        'style' => env('CONTACTS_VERIFICATION_STYLE', 'code'),
        'code_length' => env('CONTACTS_VERIFICATION_CODE_LENGTH', 6),
        'token_length' => env('CONTACTS_VERIFICATION_TOKEN_LENGTH', 32),
        'max_attempts' => env('CONTACTS_VERIFICATION_MAX_ATTEMPTS', 5),
    ],
    'types' => [
        // 'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'chat', 'rules' => ['required', 'string']],
    ],
    'relationship_kinds' => [
        // 'works_at' => 'Works at', 'spouse_of' => 'Spouse of',
    ],
];
```

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `model` | `class-string` | `Contact::class` | Model the `HasContacts` trait resolves for the `contacts()` relationship. |
| `table` | `string` | `contacts` | Database table contacts are stored in. Must be a non-empty string when set. |
| `key_type` | `string` | `bigint` (`CONTACTS_KEY_TYPE`) | Key type of the polymorphic `owner` column: `bigint`, `uuid` or `ulid` (anything else throws `InvalidConfigurationException`). Read when the migration runs, so set it before migrating. |
| `auto_primary` | `bool` | `true` | When set, the first contact of a kind added for an owner becomes its primary, and when the primary leaves a kind (deleted, synced away, moved to another kind) the next contact by position takes over. |
| `require_owner_for_primary` | `bool` | `false` | When set, only owned contacts may be primary; otherwise a `PrimaryContactConflict` is thrown. |
| `default_country_code` | `?string` | `env('CONTACTS_DEFAULT_COUNTRY_CODE')` | Best-effort dialling prefix for phone numbers entered in national format (see [Contact kinds](#contact-kinds)). Written `421`, `+421` or `1-264` (1–4 digits, no leading zero); null or empty means none. |
| `verification.ttl` | `int` | `60` (`CONTACTS_VERIFICATION_TTL`) | Minutes a verification token stays valid, 1–525600 (a year). |
| `verification.style` | `string` | `code` (`CONTACTS_VERIFICATION_STYLE`) | `code` for a numeric one-time code, `token` for a random hex string. Exact and lower-case: anything else throws, so a typo never downgrades a token to a 6-digit code. |
| `verification.code_length` | `int` | `6` (`CONTACTS_VERIFICATION_CODE_LENGTH`) | Number of digits when style is `code`, 1–72. |
| `verification.token_length` | `int` | `32` (`CONTACTS_VERIFICATION_TOKEN_LENGTH`) | Bytes of randomness when style is `token`, 1–36 (hex-encoded, so the string is twice this; bcrypt reads only the first 72 characters). |
| `verification.max_attempts` | `int` | `5` (`CONTACTS_VERIFICATION_MAX_ATTEMPTS`) | Wrong guesses a token survives, 1–1000. The guess that spends the last one voids the token. |
| `types` | `array` | `[]` | Register custom kinds and override the label/icon/rules of built-in kinds. A `kind => definition` map; `label`/`icon` are non-empty strings and `rules` a list of rule strings. |
| `relationship_kinds` | `array` | `[]` | Allow-list for the typed relationship helpers (`relateTo`/`relationsOfKind`). Empty = free-form; a list or `kind => label` map restricts kinds. |

The two switches accept env-style strings (`'true'`/`'false'`, `'1'`/`'0'`, `'on'`/`'off'`,
`'yes'`/`'no'`), and the numeric keys accept integer strings (`'30'`). A key that is absent or
`null` takes its default. Anything else — a mistyped switch or style, a TTL, length or attempt
count that is not a whole number (`'five'`, `'5.5'`, `''`) or is out of range, a blank or
non-string table, a country code that isn't one, a malformed `types` or `relationship_kinds`
entry — throws an `InvalidConfigurationException` naming the key, rather than being read as a
default or clamped. `php artisan about` shows such a value as `INVALID`.

## Contact kinds

`RoundlyConsulting\Contacts\Enums\ContactType` is a backed string enum with six kinds:
`Email`, `Phone`, `Address`, `Url`, `Social`, `Custom`. Each kind drives validation,
normalization, a translatable label, and an icon name:

- **Email** — lower-cased and trimmed, validated with the `email` rule.
- **Phone** — separators stripped, leading `+` kept, validated against an E.164-ish regex. A
  leading `00` is read as `+`, and a `(0)` after the country code is dropped
  (`+44 (0)20 7946 0000` → `+442079460000`). With `default_country_code` set (e.g. `421`), a
  national number gets it in place of its trunk `0`: `0900 123 456` → `+421900123456` (Italy and
  San Marino keep the `0`, as their numbers do). Without it, a national number keeps its bare
  digits.
- **Url** — `https://` prepended when no scheme is present, validated with the `url` rule.
- **Address / Social / Custom** — trimmed; `required|string` by default.

Rules always run on the **normalized** value — what gets stored — and every value is capped at
255 characters, the `value` column's length. A value that fails either throws
`InvalidContactValue` before anything is written.

**One primary per kind.** An owner has at most one primary contact per kind, always. With
`auto_primary` on (the default) a kind that has contacts also keeps a primary: the first one added
becomes it, and when the primary is deleted, synced away or moved to another kind, the next
contact by position is promoted (firing `PrimaryContactChanged`). With it off, nothing is promoted
for you.

### Custom kinds

Any stored kind outside the six built-ins (e.g. a `whatsapp` kind registered in config) types
as `Custom` and takes its label/icon/rules from `config('contacts.types.<kind>')`:

```php
// config/contacts.php
'types' => [
    'whatsapp' => [
        'label' => 'WhatsApp',
        'icon' => 'chat-bubble',
        'rules' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
    ],
],
```

```php
$contact = $user->addContact(ContactData::fromArray([
    'type' => 'whatsapp',
    'value' => '+421900123456',
    'name' => 'Support',
]));

$contact->kind;         // 'whatsapp'  — the raw kind, as stored
$contact->type;         // ContactType::Custom
$contact->kindLabel();  // 'WhatsApp'
$contact->kindIcon();   // 'chat-bubble'
```

The raw kind is kept alongside the type, so the registered label, icon and validation rules all
resolve — the regex above rejects a bad value on `addContact()`. `$contact->kind` is what the
registry is keyed by; `$contact->type` stays a `ContactType` for typing and is `Custom` for any
kind outside the six. Registered kinds are independent: each keeps its own primary contact, and
`Contact::query()->ofType('whatsapp')` scopes to that kind alone.

Note `kindLabel()` is the **kind's** display name, distinct from `$contact->label` — the host's
free-text label for one particular contact (e.g. "Work"). An unregistered kind is preserved but
falls back to Custom's label ("Other") and icon (`identification`).

## Usage

### The `Contacts` facade

`Contacts::for($owner)` returns the owner's **contact book**; `Contacts::verification()` runs the
token / code flow; single-contact operations are flat.

```php
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Facades\Contacts;

// Add — normalized + validated, primary guaranteed unique per kind.
Contacts::for($user)->email('a@b.com')->label('Work')->primary()->add();
Contacts::for($user)->phone('+421 900 000 000')->label('Mobile')->add();
Contacts::for($user)->type('whatsapp')->value('+421900123456')->add();   // a registered custom kind
Contacts::for($user)->add(new ContactData(ContactType::Url, 'example.com'));

// Read and export.
Contacts::for($user)->all();                        // ordered by position
Contacts::for($user)->ofType(ContactType::Email);
Contacts::for($user)->primary(ContactType::Email);  // ?Contact
Contacts::for($user)->vCard();                      // vCard 3.0 string

// One contact.
Contacts::update($contact, new ContactData(ContactType::Email, 'new@b.com'));
Contacts::setPrimary($contact);
Contacts::delete($contact);
```

| Method | Does |
|---|---|
| `for($owner)` | the owner's `ContactBook` (below) |
| `verification()` | the `ContactVerification` accessor: `request()`, `confirm()`, `markVerified()` |
| `update($contact, ContactData)` | overwrite a contact; a primary flag promotes it. A new value or kind drops the verification. |
| `setPrimary($contact)` | promote a contact, demoting its owner's other primary of the same kind |
| `delete($contact)` | soft-delete a contact; deleting the primary promotes the next one (`auto_primary`) |
| `sharedWith(Connectable $owner)` | contacts connected to an owner (see connections below) |
| `validationRules(string $key = 'contacts')` | `contacts.*` rules for a repeatable form |
| `fake()` | swap in `ContactsFake` (see Testing helper) |

`ContactBook` (`Contacts::for($owner)`): `email()`, `phone()`, `url()`, `address()`,
`structuredAddress()`, `type()` start a fluent `PendingContact` (then `label()`, `name()`,
`category()`, `value()`, `primary()`, `meta()`, and `add()`); `add(ContactData)`,
`sync(ContactType, list<ContactData>)`, `all()`, `ofType()`, `primary()` and `vCard()`.

### Without the facade

The facade is sugar over `ContactsManager` — inject it for the same API, or call an action
directly:

```php
use RoundlyConsulting\Contacts\Actions\AddContactAction;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;

public function __construct(private ContactsManager $contacts) {}

$this->contacts->for($user)->email('a@b.com')->primary()->add();
$this->contacts->verification()->request($contact);

// The raw action
app(AddContactAction::class)->execute($user, new ContactData(ContactType::Email, 'a@b.com'));
```

The actions are `AddContactAction`, `UpdateContactAction`, `DeleteContactAction`,
`SetPrimaryContactAction`, `SyncContactsAction`, `RequestContactVerificationAction`,
`ConfirmContactVerificationAction` and `VerifyContactAction`.

### Trait sugar

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
$user->addEmail('A.Person@Example.COM ', label: 'Work', primary: true);
$user->addPhone('+421 900 000 000', label: 'Mobile');
$user->addUrl('example.com');
$user->addAddress('12 Main St');

$user->primaryEmail()?->value;          // 'a.person@example.com'
$user->primaryPhone()?->value;          // '+421900000000'
$user->contactsOfType('phone');         // ordered EloquentCollection
$user->contactBook();                   // same as Contacts::for($user)
```

Every trait method goes through the manager, so `Contacts::fake()` records it.

### Verifying a contact (token / code flow)

The package generates a verification token, stores **only its hash** plus an expiry, and
fires an event with the plaintext so your app delivers it over its own channel (mail, SMS,
…). The package never sends anything.

```php
use RoundlyConsulting\Contacts\Facades\Contacts;

// Generate a token and dispatch ContactVerificationRequested($contact, $plainToken).
$token = Contacts::verification()->request($contact);   // or $contact->requestVerification()

// Later, confirm with the token the user supplied.
Contacts::verification()->confirm($contact, $token);     // or $contact->confirmVerification($token)
// On success: verified_at is set, token fields cleared, ContactVerified fires.

// Verified out of band (no token): set verified_at and fire ContactVerified.
Contacts::verification()->markVerified($contact);         // optional ?CarbonInterface $at
```

Deliver the token from a listener:

```php
use RoundlyConsulting\Contacts\Events\ContactVerificationRequested;

Event::listen(function (ContactVerificationRequested $event): void {
    // $event->contact, $event->plainToken — send your own mail/SMS here.
});
```

Confirmation throws `InvalidVerificationToken` (wrong, absent or voided token) or
`VerificationExpired` (past the TTL). Both extend `ContactException`. Token TTL and style (numeric
`code` vs random `token`) are configured under `contacts.verification`. The factory ships a
`pendingVerification()` state and a `pendingVerification()` query scope.

Guarding the code:

- **Attempt limit.** Each token survives `verification.max_attempts` (default 5) wrong guesses.
  The guess that spends the last one voids the token and throws `VerificationAttemptsExceeded` — a
  subclass of `InvalidVerificationToken`, so one `catch` covers both; ask for a new token then. The
  count lives in the database, so parallel requests share one budget. A new `request()` starts a
  fresh budget, so rate-limit how often your app lets a user request a token (e.g. Laravel's
  `RateLimiter`).
- **A verification belongs to one value.** Changing a contact's value or kind — through
  `update()`, `sync()` or a direct `$contact->update([...])` — clears `verified_at` and voids any
  pending token, so a token sent to the old address can never confirm the new one. A write that
  sets `verified_at` itself (e.g. an import) is kept.

### Syncing a set of contacts (e.g. a profile form)

```php
Contacts::for($user)->sync(ContactType::Phone, [
    new ContactData(ContactType::Phone, '+421900000000', label: 'Mobile', isPrimary: true),
    new ContactData(ContactType::Phone, '+421900111222', label: 'Office'),
]);
```

`sync()` updates matching values in place, creates new ones, deletes the rest, and assigns
`position` by input order. If the primary is among the deleted and no item asks for `isPrimary`,
the first synced contact becomes the primary (`auto_primary`).

### Reusing validation in a FormRequest

`ValidContactValue` is a standalone, reusable `ValidationRule` — apply it to any field:

```php
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;

$request->validate([
    'email' => ['required', new ValidContactValue(ContactType::Email)],
]);
```

`ContactType::rules()` returns a ready rule array — the kind's field rules (`required`,
`string`, …) plus `ValidContactValue`, which checks the **normalized** value — so a FormRequest
stays a one-liner and accepts exactly what `addEmail()` / `addPhone()` / `addUrl()` accept
(`' A.Person@Example.COM '`, `'+421 900 000 000'`, `'example.com'`):

```php
public function rules(): array
{
    return [
        'email' => ContactType::Email->rules(),       // or ContactType::rulesFor(ContactType::Email)
        'phone' => ContactType::Phone->rules(),
    ];
}
```

For a registered custom kind use `ContactRules::forValue(ContactType::Custom, 'whatsapp')`. The
validated input is still the raw string; the package normalizes it when you add it.

For a repeatable list of `{type, value}` pairs (e.g. a "manage contacts" form), generate
`contacts.*` rules: each `type` must be a built-in or registered kind, and each `value` is
normalized and validated against the rules of the kind its entry declares:

```php
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Support\ContactRules;

$request->validate(Contacts::validationRules());       // keys: contacts, contacts.*.type, contacts.*.value
$request->validate(ContactRules::forArray('people'));  // custom prefix
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
Contact::query()->pendingVerification()->get();        // unverified, token issued
Contact::query()->search('alice')->get();              // name / value / label LIKE
Contact::query()->ordered()->get();                    // by position, then id
```

### Events

Each write dispatches an event so host apps can react (send a verification email, sync a
CRM, …) without forking:

`ContactAdded`, `ContactUpdated`, `ContactDeleted`, `ContactVerified`, `PrimaryContactChanged`
— each carries the affected `Contact` (and the previous primary, where relevant).
`ContactVerificationRequested` additionally carries the plaintext token for delivery.

### Routing notifications through contacts

Opt in by adding `RoutesNotificationsViaContacts` (alongside `HasContacts`) to a model. It
resolves Laravel notification destinations from the owner's **primary** contacts:

```php
use RoundlyConsulting\Contacts\Concerns\HasContacts;
use RoundlyConsulting\Contacts\Concerns\RoutesNotificationsViaContacts;

class User extends Model
{
    use HasContacts;
    use RoutesNotificationsViaContacts;
}
```

It implements `routeNotificationForMail()` (primary email), `routeNotificationForVonage()`
and `routeNotificationForTwilio()` (primary phone), each returning `null` when there's no
primary of that kind so Laravel simply skips that channel.

**Precedence:** the trait is intentionally separate from `HasContacts` and is **not** applied
automatically, because host models often already use `Notifiable`. When you do add it, these
`routeNotificationFor*()` methods take precedence over Notifiable's attribute-based routing
for the mail, Vonage, and Twilio channels.

### vCard export

```php
Contacts::for($user)->vCard();   // a vCard 3.0 string for the owner's contacts
$contact->toVCard();             // a single contact
```

The card is valid vCard 3.0 (RFC 2426):

- **`N` and `FN`** are always present. `N` is built from the owner's structured name attributes
  when it has them — `last_name`/`family_name`/`surname`, `first_name`/`given_name`,
  `middle_name`/`additional_name`, `name_prefix`/`honorific_prefix`,
  `name_suffix`/`honorific_suffix`. Otherwise the display name fills `N`'s family-name slot
  (`N:Jane Doe;;;;`). `FN` is the owner's `name`, else the structured parts, else `Contact`.
  Only attributes the model actually has are read, so `Model::shouldBeStrict()` is safe.
- **Labels:** a label naming a registered type for its property (`work`, `home`, `cell`, `fax`,
  `pager`, …; `mobile` becomes `cell`) is emitted as `TYPE=work`. Any other label
  (`Office, main`) is kept as `item1.X-ABLabel:Office\, main` on a grouped property — the
  custom-label form Apple and Google contacts read — rather than as an invalid `TYPE`.
- Text is escaped (`\`, `,`, `;`, and any CRLF/CR/LF as `\n`), URLs are left unescaped, and long
  lines fold at 75 octets without splitting a UTF-8 character.

### Testing helper

`Contacts::fake()` swaps the manager for `ContactsFake` — injected managers get it too. Nothing
is written and no event fires: added contacts come back unsaved (a structured address is rendered
into the value, never stored). Every write is recorded — through the facade, an injected manager,
a contact book, `verification()`, the `HasContacts` trait or `$contact->requestVerification()` /
`confirmVerification()`. Reads still hit the database.

```php
use RoundlyConsulting\Contacts\Facades\Contacts;

$fake = Contacts::fake();

$user->addEmail('a@b.com');
$contact->requestVerification();

$fake->assertAdded(fn (ContactData $data, Model $owner) => $data->value === 'a@b.com');
$fake->assertVerificationRequested($contact);
$fake->assertNothingDeleted();
```

| Records | Assert | Assert none |
|---|---|---|
| `for()->…->add()`, `for()->add()`, trait `add*()` | `assertAdded(?Closure(ContactData, Model))` | `assertNothingAdded()` |
| `for()->sync()` | `assertSynced(?ContactType, ?Closure(list<ContactData>, Model))` | `assertNothingSynced()` |
| `update()` | `assertUpdated(?Contact, ?Closure(ContactData))` | `assertNothingUpdated()` |
| `delete()` | `assertDeleted(?Contact)` | `assertNothingDeleted()` |
| `setPrimary()` | `assertPrimarySet(?Contact)` | `assertNothingPrimarySet()` |
| `verification()->markVerified()` | `assertVerified(?Contact)` | `assertNothingVerified()` |
| `verification()->request()`, `$contact->requestVerification()` | `assertVerificationRequested(?Contact)` | `assertNoVerificationRequested()` |
| `verification()->confirm()`, `$contact->confirmVerification()` | `assertVerificationConfirmed(?Contact)` | `assertNoVerificationConfirmed()` |

Model factories ship states for ergonomic test setup: `email()`, `phone()`, `url()`,
`address()`, `primary()`, `verified()`, `pendingVerification()`, `forOwner($model)`, and
`ofType($type)`.

### Pest expectations

Register the package's custom Pest matchers from your app's `tests/Pest.php`:

```php
use RoundlyConsulting\Contacts\Testing\ContactExpectations;

ContactExpectations::register();
```

Then assert against any owner model:

```php
use RoundlyConsulting\Contacts\Enums\ContactType;

expect($company)
    ->toHaveContactOfType(ContactType::Email)
    ->toHavePrimaryEmail('hello@acme.test')
    ->toHavePrimaryContact(ContactType::Phone, '+421900000000')
    ->toHaveVerifiedContact('hello@acme.test');
```

### Low-level access

The relation stays available for direct use: `contacts()`, its query scopes, the `meta`
collection cast, and `$user->contacts()->create([...])`. Normalization, validation, and
auto-primary apply only through the action / facade / trait-sugar path. Dropping the
verification on a value change applies everywhere, direct writes included.

Contacts use soft deletes, so a deleted contact stays retrievable via `withTrashed()`. A soft
delete trashes the contact's structured addresses and connections with it, and `restore()` brings
back exactly those (not ones you had deleted separately before). A restored primary comes back as
a secondary if its kind promoted another contact meanwhile. `forceDelete()` removes the addresses
and connections for good.

```php
Contacts::delete($contact);
Contact::withTrashed()->find($contact->id)->restore();   // addresses + connections are back
```

## Integrates with

This package hard-requires five roundly packages (wired automatically), turning a flat contact
list into a small CRM-grade address book + relationship graph.

- **[addresses-for-laravel](https://github.com/roundly-consulting/addresses-for-laravel)** —
  structured, validated postal addresses on a `Contact`.
- **[connections-for-laravel](https://github.com/roundly-consulting/connections-for-laravel)** —
  contact ↔ contact and owner ↔ contact affiliations.
- **[crypto-for-laravel](https://github.com/roundly-consulting/crypto-for-laravel)** — the
  CSPRNG draw and hex encoding behind verification tokens and codes.
- **[enums-for-laravel](https://github.com/roundly-consulting/enums-for-laravel)** — select/label
  helpers on `ContactType`.
- **[package-toolkit-for-laravel](https://github.com/roundly-consulting/package-toolkit-for-laravel)**
  — the service-provider builder (config/migrations/translations/facade-alias wiring + the
  `php artisan about` section) and the validated `contacts.model` resolver.

The package reports its configuration to Laravel's `about` command — registered kinds and
relationship allow-lists are reported as counts, never as their contents:

```bash
php artisan about --only=contacts
```

### Structured addresses (addresses)

A `Contact` is `Addressable`, so it holds a billing/physical/mailing address book. The loose
`value` string keeps working as a fallback.

```php
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;

$contact->addAddress(AddressData::make(
    city: 'Bratislava', street: 'Hlavna 1', postalCode: '81101',
    countryIso: 'SK', type: AddressType::Billing, isPrimary: true,
));

$contact->primaryAddress();                       // the primary Address
$contact->addressesOfType(AddressType::Billing);  // typed lookup
$contact->formattedAddress();                     // one-line render, falls back to value

// Build an address-type contact and its structured Address in one call:
Contacts::for($owner)
    ->structuredAddress([
        'city' => 'Vienna', 'street' => 'Ring 3',
        'postalCode' => '1010', 'countryIso' => 'AT',
        'type' => AddressType::Billing,
    ])
    ->add();

// Or from the owner directly:
$owner->addStructuredAddress(AddressData::make(/* … */), label: 'Main');
```

The contact's `value` mirrors the address's one-line render. A structured address is only
accepted on an address contact — anything else throws `InvalidContactValue`.

### Affiliations & relationships (connections)

A `Contact` is `Connectable`. Link contacts to each other and to owners, with a free-form,
host-config-driven relationship "kind".

```php
$person->connectTo($company, ['employee']);       // raw connection
$person->relateTo($company, 'works_at');           // typed kind, stored in meta
$person->relationsOfKind('works_at');              // contacts related under a kind
$person->inviteConnection($company);               // pending → accept/block
$company->acceptConnectionFrom($person);

Contacts::sharedWith($owner);                       // contacts connected to an owner
```

Restrict the allowed kinds with the `contacts.relationship_kinds` config (empty = free-form).

### `ContactType` helpers (enums)

`ContactType` adopts the enums `Helpers` trait on top of its domain methods (the config/lang-aware
`label()` is preserved):

```php
ContactType::values();          // ['email', 'phone', …]
ContactType::options();         // EnumOption DTOs for selects
ContactType::validationRule();  // 'in:email,phone,address,url,social,custom'
$type->isIn([ContactType::Email, ContactType::Phone]);
```

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=contacts-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=contacts-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
