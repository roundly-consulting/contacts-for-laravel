<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\Exceptions\ContactException;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Contacts shipped exactly one architecture rule — a debugging-function ban — so every
 * preset below is a new guard rather than a replacement. The ban itself is kept, now as
 * `noDebuggingLeftovers`, which is strictly stronger: the hand-written version went
 * through Pest's arch layer, which only sees a dependency whose symbol *exists*, and
 * `ray` is not in the dependency graph by policy — so `ray` was filtered out before the
 * ban ran and could never fail. The preset reads source tokens, which don't care.
 */
ArchPresets::strictTypes('RoundlyConsulting\Contacts');

/**
 * The deliberate extension points are exempt: `Contact` is what `contacts.model` invites
 * a host to subclass (pinned by the preset below instead), ContactException is the base
 * every contacts error extends so a host can catch them uniformly, and ContactsManager is
 * extended by the package's own shipped `FakeContactsManager` — a host-facing test double,
 * so the openness is part of the published surface rather than an oversight.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Contacts')
    ->ignoring([
        Contact::class,
        ContactException::class,
        ContactsManager::class,
    ]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable
 * model is a PHP fatal the moment a host uses the seam the config documents. The preset
 * also pins that `contacts.model` really defaults to the packaged Contact, so the seam
 * cannot rot in the other direction either.
 */
ArchPresets::swappableModelsAreNotFinal([
    Contact::class => 'contacts.model',
]);

/**
 * Every cryptographic primitive belongs in crypto-for-laravel, never hand-rolled here —
 * and now with NO EXEMPTIONS, which is the point.
 *
 * `RequestContactVerificationAction::generateToken()` mints the token that proves ownership
 * of an email address or phone number. It used to draw that locally with
 * `bin2hex(random_bytes($n))` and `random_int(0, 10**$n - 1)` — respectively
 * crypto-for-laravel's `Random\Bytes::generate()` + `Codec\Hex::encode()` and, line for
 * line, `Random\Token::numeric()`. It now calls those, so the class needs no exemption.
 *
 * The restored coverage is the real gain. Pest's exemptions are scoped to a **class**, not
 * a function: exempting this class for its two CSPRNG calls blinded the class that mints
 * the package's only secret to the other 17 banned primitives. A token-scan re-imposing the
 * rest used to stand here to buy that back; routing through crypto deleted the exemption
 * and the workaround together, and the shared preset guards this class directly.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Contacts');

/**
 * Every `contacts.model` read goes through Support\ContactModel (which delegates to the
 * toolkit's ModelResolver). Adopted rather than rejected as jwt rejected it: contacts has
 * exactly the shape the preset targets — a real Eloquent model behind a `*model` key,
 * resolved through a Support seam — so the stray-literal half has something to say.
 *
 * The key is conventionally shaped (`model`), so inference already covers it; declaring
 * it anyway costs nothing and makes the coverage explicit rather than incidental, and
 * declared keys are unioned with inferred ones.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../../src', 'Support', ['contacts.model']);

/**
 * The morph-key seam, guarded. Contacts migrated its owner morph column off raw
 * `$table->morphs()` onto `morphKey($name, KeyType::…)` so a uuid/ulid host can flip its
 * whole graph coherently — a hardcoded bigint id breaks those hosts on Postgres, and SQLite
 * type affinity hides it. This pin reds if a future migration reintroduces a raw morph and
 * bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: contacts' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If this goes red, the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../../composer.json');

ArchPresets::noDebuggingLeftovers();
