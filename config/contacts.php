<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Models\Contact;

return [

    /*
    |--------------------------------------------------------------------------
    | Contact Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store contacts. Override this with your own
    | model (extending the package's Contact) if you need custom behaviour.
    |
    */

    'model' => Contact::class,

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | The database table contacts are stored in. Change this if "contacts"
    | clashes with an existing table in your application.
    |
    */

    'table' => 'contacts',

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic owner column. Use "uuid" or "ulid"
    | when the owner models use UUID/ULID primary keys, otherwise leave it as
    | "bigint". Any unrecognized value falls back to "bigint". It is fixed when
    | the migration first runs, so choose it before publishing the migrations.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('CONTACTS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Primary
    |--------------------------------------------------------------------------
    |
    | When enabled, the first contact of a given kind added for an owner
    | automatically becomes that owner's primary contact for the kind.
    |
    */

    'auto_primary' => true,

    /*
    |--------------------------------------------------------------------------
    | Require Owner For Primary
    |--------------------------------------------------------------------------
    |
    | When enabled, only owned contacts may be marked primary; attempting to
    | set an owner-less contact as primary throws a PrimaryContactConflict.
    |
    */

    'require_owner_for_primary' => false,

    /*
    |--------------------------------------------------------------------------
    | Default Country Code
    |--------------------------------------------------------------------------
    |
    | Best-effort dialling prefix for phone numbers entered in national format
    | (no leading "+" or "00"): "0900 123 456" becomes "+421900123456" — the
    | trunk 0 is dropped, except for Italy/San Marino, where it is part of the
    | number. A leading "00" is always read as "+". Leave null to store the
    | bare digits of a national number. Example: "421".
    |
    */

    'default_country_code' => env('CONTACTS_DEFAULT_COUNTRY_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    |
    | Controls the channel-agnostic verification token/code flow. Only a hash
    | of the token is ever stored. Delivery is up to the host app, which
    | listens for the ContactVerificationRequested event.
    |
    | - ttl: how many minutes a generated token stays valid.
    | - style: "code" for a numeric one-time code, "token" for a random string.
    | - code_length: number of digits when style is "code" (1-72).
    | - token_length: number of bytes of randomness when style is "token"
    |   (the token is hex-encoded, so the string is twice this length; 1-36,
    |   because bcrypt reads only the first 72 characters).
    | - max_attempts: wrong guesses a token survives; the guess that spends the
    |   last one voids it (VerificationAttemptsExceeded) and a new request is
    |   needed. Counted in the database, so parallel requests share the budget.
    |
    */

    'verification' => [
        'ttl' => (int) env('CONTACTS_VERIFICATION_TTL', 60),
        'style' => env('CONTACTS_VERIFICATION_STYLE', 'code'),
        'code_length' => (int) env('CONTACTS_VERIFICATION_CODE_LENGTH', 6),
        'token_length' => (int) env('CONTACTS_VERIFICATION_TOKEN_LENGTH', 32),
        'max_attempts' => (int) env('CONTACTS_VERIFICATION_MAX_ATTEMPTS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Types
    |--------------------------------------------------------------------------
    |
    | Register custom kinds and override the label, icon, or validation rules
    | of the built-in kinds (email, phone, address, url, social, custom).
    |
    | A kind outside the six built-ins types as Custom ($contact->type), while
    | the raw kind is kept on $contact->kind — that is what this registry is
    | keyed by, so it drives the label, icon and rules below.
    |
    | Example:
    |   'whatsapp' => [
    |       'label' => 'WhatsApp',
    |       'icon' => 'chat-bubble',
    |       'rules' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
    |   ],
    |
    | With the above, a contact added as 'whatsapp' reports kindLabel()
    | "WhatsApp", kindIcon() "chat-bubble", and is validated against the regex.
    |
    */

    'types' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Relationship Kinds
    |--------------------------------------------------------------------------
    |
    | The typed relationship helpers (relateTo / relationsOfKind) store a "kind"
    | on each connection (works_at, spouse_of, reports_to, member_of, …). When
    | this list is non-empty it acts as an allow-list — relating a contact under
    | any other kind throws a RelationshipException. Leave it empty to keep kinds
    | free-form. Accepts a plain list of kinds or a kind => label map.
    |
    | Example:
    |   'relationship_kinds' => [
    |       'works_at' => 'Works at',
    |       'spouse_of' => 'Spouse of',
    |       'reports_to' => 'Reports to',
    |   ],
    |
    */

    'relationship_kinds' => [
        //
    ],

];
