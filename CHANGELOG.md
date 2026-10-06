# Changelog

All notable changes to `contacts-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Added

- `HasContacts::addAddressContact(string $value, ?string $label = null, bool $primary = false)` adds
  a free-text address contact under a name that does not collide with addresses'
  `HasAddresses::addAddress()`.
- `Contacts::for($owner)->sync()` takes a raw kind as well as a `ContactType`, so a registered
  custom kind syncs as itself: `->sync('whatsapp', [...])` reconciles the owner's whatsapp contacts
  and stores every item as `whatsapp`. `Contacts::fake()->assertSynced()` takes the raw kind too.

### Changed

- Documentation: the `default_country_code` config comment now says what happens without a country
  code — a national number with a trunk 0 (`0900 123 456`) is rejected as an invalid phone, one
  without it is stored as its digits. Behaviour is unchanged.
- The structured address `add()` attaches (`structuredAddress()`, `addStructuredAddress()`,
  `ContactData::$address`) is now the contact's primary `Address`, so `primaryAddress()` finds it
  and `formattedAddress()` renders it — later edits to the address included — instead of falling
  back to the mirrored `value`.
- The `@internal` `ContactsManager::syncFor()` (and `ContactsFake::syncFor()`) now types its kind as
  `ContactType|string`; a host subclass overriding it must widen its parameter the same way.

### Deprecated

- `HasContacts::addAddress()` — use `addAddressContact()`; the alias is removed in 2.0. Until then a
  model using both `HasContacts` and `HasAddresses` resolves the collision with `use HasAddresses,
  HasContacts { HasAddresses::addAddress insteadof HasContacts; }`.

### Fixed

- A structured address given as an array (`structuredAddress([...])`,
  `ContactData::fromArray(['address' => [...]])`) keeps its `meta` array instead of dropping it.
- `ContactFactory` builds the model configured in `contacts.model`, so `Contact::factory()` and a
  host subclass's `factory()` create the host's model (its casts, events and observers run).
- `Contacts::setPrimary()` refuses a soft-deleted contact with `PrimaryContactConflict::trashed()`
  instead of demoting the live primary and leaving the kind without one.
- `PendingContact::structuredAddress()` no longer turns an explicitly chosen kind
  (`->type('whatsapp')`, `->type(ContactType::Custom)`) into an address: `add()` refuses it with
  `InvalidContactValue::structuredAddressOn()` whatever the call order. Only a builder with no kind
  chosen defaults to address.
- `relationsOfKind()` honours `connections.enforce_active_on_check` like the connections listing
  helpers: while it is on (the default), blocked, pending and expired relationships are left out.
  Turn the flag off to list them again.
- `Contacts::update()` honours `ContactData::$address` like `add()` does: a structured address on a
  non-address kind is refused (`InvalidContactValue::structuredAddressOn()`), a blank value becomes
  the address's render, and the contact's structured `Address` is replaced in the same transaction
  as the row, so `formattedAddress()` renders the new one. A value-only update keeps the structured
  address.
- `sync()` matches a structured-address item (blank value + `address:`) by its rendered address, so
  syncing the same address again updates the contact in place instead of deleting and re-adding it
  (new id, lost verification, cascade-trashed address and connections).

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Typed contacts on any Eloquent model via the `HasContacts` trait: `addEmail()`, `addPhone()`,
  `addUrl()`, `addAddress()`, `primaryEmail()`, `primaryPhone()` and `contactsOfType()`.
- Six built-in kinds in the `ContactType` enum (email, phone, address, URL, social, custom) plus
  custom kinds registered in config, each with its own label, icon and validation rules.
- Automatic normalization and validation of every value (national phone formats included, capped
  at the 255-character column), one primary contact per kind — kept through updates, deletes and
  syncs, with the next contact promoted while `auto_primary` is on — and ordering by position.
- Soft deletes that trash a contact's structured addresses and connections with it; `restore()`
  brings them back and `forceDelete()` removes them.
- A `Contacts` facade over an injectable `ContactsManager`. `Contacts::for($owner)` returns the
  owner's contact book: fluent adds (`->email(...)->primary()->add()`, `->phone()`, `->url()`,
  `->address()`, `->structuredAddress()`, `->type()`), `add(ContactData)`, `sync()` to reconcile a
  whole set of contacts from a form, and `all()`, `ofType()`, `primary()`, `vCard()` reads. Flat
  `update()`, `setPrimary()`, `delete()`, `sharedWith()` and `validationRules()`.
- Verification by token or numeric code through `Contacts::verification()->request()` /
  `->confirm()` (or `$contact->requestVerification()` / `confirmVerification()`), plus
  `->markVerified()` for out-of-band checks. Only a hash is stored; delivery is left to your own
  mail or SMS listener. Each token survives `verification.max_attempts` wrong guesses (default 5,
  counted in the database) before it is voided with `VerificationAttemptsExceeded`, and changing a
  contact's value or kind drops its verification and any pending token.
- Reusable validation on the normalized value: the `ValidContactValue` rule, `ContactType::rules()`
  and `Contacts::validationRules()` for repeatable contact lists, which checks each value against
  the kind its entry declares.
- Query scopes (`forOwner()`, `ofType()`, `primary()`, `verified()`, `search()`, `ordered()`, …)
  and events for every write (`ContactAdded`, `ContactVerified`, `PrimaryContactChanged`, …).
- The opt-in `RoutesNotificationsViaContacts` trait routes mail, Vonage and Twilio notifications to
  the owner's primary contacts.
- vCard 3.0 export for an owner (`Contacts::for($user)->vCard()`) or a single contact (`toVCard()`).
- Structured postal addresses and contact-to-contact relationships through the addresses and
  connections companion packages (`addStructuredAddress()`, `relateTo()`, `Contacts::sharedWith()`).
- `Contacts::fake()` (`ContactsFake`, a `ContactsManager` subtype, so injected managers get it too)
  records every write — including the `HasContacts` trait and `Contact` verification methods —
  and writes nothing, not even a structured address. Asserts: `assertAdded`, `assertSynced`,
  `assertUpdated`, `assertDeleted`, `assertPrimarySet`, `assertVerified`,
  `assertVerificationRequested`, `assertVerificationConfirmed`, each with an `assertNothing*` /
  `assertNo*` counterpart. Model factory states and Pest expectations such as
  `toHavePrimaryEmail()` round out the test kit.
- `ContactData` carries an optional structured `address`; `$user->contactBook()` returns the same
  book as `Contacts::for($user)`.
