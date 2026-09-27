# Changelog

All notable changes to `contacts-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Typed contacts on any Eloquent model via the `HasContacts` trait: `addEmail()`, `addPhone()`,
  `addUrl()`, `addAddress()`, `primaryEmail()`, `primaryPhone()` and `contactsOfType()`.
- Six built-in kinds in the `ContactType` enum (email, phone, address, URL, social, custom) plus
  custom kinds registered in config, each with its own label, icon and validation rules.
- Automatic normalization and validation of every value, one primary contact per kind, and
  ordering by position.
- A fluent `Contacts` facade — `Contacts::for($user)->phone(...)->primary()->add()` — and
  `Contacts::sync()` to reconcile a whole set of contacts from a form.
- Verification by token or numeric code: `requestVerification()` / `confirmVerification()`, with
  only a hash stored and delivery left to your own mail or SMS listener.
- Reusable validation: the `ValidContactValue` rule, `ContactType::rules()` and
  `Contacts::validationRules()` for repeatable contact lists.
- Query scopes (`forOwner()`, `ofType()`, `primary()`, `verified()`, `search()`, `ordered()`, …)
  and events for every write (`ContactAdded`, `ContactVerified`, `PrimaryContactChanged`, …).
- The opt-in `RoutesNotificationsViaContacts` trait routes mail, Vonage and Twilio notifications to
  the owner's primary contacts.
- vCard 3.0 export for an owner (`Contacts::vCard($user)`) or a single contact (`toVCard()`).
- Structured postal addresses and contact-to-contact relationships through the addresses and
  connections companion packages (`addStructuredAddress()`, `relateTo()`, `Contacts::sharedWith()`).
- `Contacts::fake()`, model factory states and Pest expectations such as `toHavePrimaryEmail()` for
  your tests.
