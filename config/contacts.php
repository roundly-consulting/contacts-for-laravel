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
    | Best-effort dialling prefix prepended to phone numbers entered without a
    | leading "+". Leave null to store bare digits. Example: "421".
    |
    */

    'default_country_code' => env('CONTACTS_DEFAULT_COUNTRY_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Contact Types
    |--------------------------------------------------------------------------
    |
    | Register custom kinds and override the label, icon, or validation rules
    | of the built-in kinds (email, phone, address, url, social, custom). Any
    | stored type outside the six built-ins is treated as the Custom kind.
    |
    | Example:
    |   'whatsapp' => [
    |       'label' => 'WhatsApp',
    |       'icon' => 'chat-bubble',
    |       'rules' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
    |   ],
    |
    */

    'types' => [
        //
    ],

];
