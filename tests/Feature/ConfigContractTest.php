<?php

declare(strict_types=1);

/**
 * The config contract contacts never had, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`;
 *    330 tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead
 *    config that lies to the host: media #27's `max_file_size` cap that never applied,
 *    alerts #24's thrice-documented `escalation` key. Contacts' config is unusually
 *    exposed to this — it documents four verification knobs and two extension registries,
 *    each with a worked example in a comment block, and a host reading
 *    `contacts.verification.ttl` in the file believes tokens expire.
 *
 * The suite's existing `ConfigTest.php` asserts a handful of defaults read back. That is
 * a different thing entirely: it proves the file is merged, not that the file and the
 * code agree about which keys exist.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/contacts.php')->toSatisfyConfigContract(
        [__DIR__.'/../../src', __DIR__.'/../../database'],
        [
            // `contacts.model` is read through the toolkit's `ModelResolver::for('contacts.model')`
            // seam (via Support\ContactModel) rather than a `config()` call. It is a real
            // read — it drives the model swap — but it is not a `config(` token, so the
            // prefix is what makes it visible to the scraper.
            'extraReadPrefixes' => ['contacts.'],

            // Deliberately NO `excludeFromReverse` for the provider. The testing README's
            // own example excludes the service provider on the grounds that "a render is
            // not a read" — but this provider's `contributesToAbout()` closure calls
            // `config('contacts.…')` for real (table, auto_primary,
            // require_owner_for_primary, default_country_code, verification.*, types,
            // relationship_kinds), and for several of those it is the only reader in the
            // package. Excluding it would discard readers and weaken the reverse
            // direction for nothing.
        ],
    );
});
