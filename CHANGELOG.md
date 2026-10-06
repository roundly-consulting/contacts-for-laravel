# Changelog

All notable changes to `contacts-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Fixed

- A structured address given as an array (`structuredAddress([...])`,
  `ContactData::fromArray(['address' => [...]])`) keeps its `meta` array instead of dropping it.
- `ContactFactory` builds the model configured in `contacts.model`, so `Contact::factory()` and a
  host subclass's `factory()` create the host's model (its casts, events and observers run).

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
