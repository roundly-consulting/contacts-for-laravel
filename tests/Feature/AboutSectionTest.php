<?php

declare(strict_types=1);

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`,
 * which returns `''`. Every "does not leak" check was vacuous — passing against empty
 * output. This capture goes through `Artisan::call('about')` and asserts the output is
 * non-empty and renders every `mustRender` string *before* it looks for a secret.
 *
 * Contacts carries no API credentials, which is exactly why it is worth pinning: the
 * risk here is not a key but **the host's own address book**. The provider is
 * deliberately written to report the registered kinds and the relationship allow-list
 * **by count** — because a custom kind ("court-summons-address") and a relationship kind
 * ("informant_for") are host domain vocabulary that leak intent, and the country code by
 * presence. This test is what stops a future "helpful" change from rendering them.
 */
it('renders the contacts section without leaking the host domain vocabulary', function (): void {
    config()->set('contacts.default_country_code', '421');
    config()->set('contacts.auto_primary', false);
    config()->set('contacts.require_owner_for_primary', true);
    config()->set('contacts.verification.style', 'token');
    config()->set('contacts.verification.ttl', 15);
    config()->set('contacts.types', [
        'court-summons-address' => ['label' => 'Court summons address'],
        'informant-burner-phone' => ['label' => 'Burner'],
    ]);
    config()->set('contacts.relationship_kinds', [
        'informant_for' => 'Informant for',
        'under_investigation_by' => 'Under investigation by',
    ]);

    expect('contacts')->toLeakNoSecrets(
        secrets: [
            // A registered kind is the host's private domain vocabulary — the section
            // reports how many are registered, never which.
            'court-summons-address',
            'informant-burner-phone',
            // A relationship kind is the same class of secret, and a more revealing one.
            'informant_for',
            'under_investigation_by',
            // The dialling prefix is reported by presence (SET/NONE), never its value.
            '421',
        ],
        mustRender: [
            'Model',
            'Table',
            'Auto primary',
            'Require owner for primary',
            'Default country code',
            'Verification',
            'Custom types',
            'Relationship kinds',
            // The positive halves that prove the lines report rather than sit silently
            // empty: the counts themselves, the presence marker, and both switch states.
            '2 registered',
            '2 allowed',
            'SET',
            'OFF',
            'ON',
            'token, 15m',
        ],
    );
});

/**
 * The other side of the two count lines: an empty relationship allow-list is a *mode*
 * (kinds are free-form), not an absence, and the section says so. Pinned because the
 * FREE-FORM branch is the shipped default — the branch every host sees first is the one
 * a leak-only test would never render.
 */
it('reports the free-form relationship default without leaking', function (): void {
    expect('contacts')->toLeakNoSecrets(
        secrets: ['informant_for'],
        mustRender: [
            'Relationship kinds',
            'FREE-FORM',
            '0 registered',
            // The country code is unset by default and must report NONE, not an empty
            // line that reads like a rendering bug.
            'NONE',
            'code, 60m',
        ],
    );
});
