<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Actions\RequestContactVerificationAction;
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
 * Every cryptographic primitive belongs in crypto-for-laravel, never hand-rolled here.
 * Contacts had no arch rule of this kind at all, so this preset is a new guard — and it
 * went red on its first run, on the one class where it matters.
 *
 * ONE exemption, and — following the two-factor precedent — it is a **deferral, not a
 * clean bill of health**.
 *
 * `RequestContactVerificationAction::generateToken()` mints the token that proves
 * ownership of an email address or phone number. It draws it locally with
 * `bin2hex(random_bytes($n))` and `random_int(0, 10**$n - 1)`, which are respectively
 * crypto-for-laravel's `Random\Bytes::generate()` and — line for line, including the
 * uniform draw and the exact length — `Random\Token::numeric()`. That is the duplication
 * this preset exists to catch, and unlike httprl's jitter exemption it IS a security
 * boundary: this is the secret, not a retry offset.
 *
 * It is **not a vulnerability**: `random_bytes`/`random_int` are CSPRNGs, the numeric draw
 * is uniform, and only `Hash::make($plain)` is ever stored. Collapsing it onto crypto is a
 * pure refactor — but it is a refactor of verification-token generation AND it would add
 * crypto-for-laravel to this package's runtime `require` (contacts does not depend on it
 * today; the tier DAG permits it, crypto being Tier 1 to contacts' Tier 2). Both are
 * decisions, so this waits for one rather than being slipped into an adoption row.
 *
 * The cost of the exemption is that Pest's `->ignoring()` is **class-scoped**, not
 * function-scoped: exempting this class to permit two primitives blinds it to all twenty —
 * in the class that mints the package's only secret. So the ban is re-imposed below by
 * token scan, minus the two calls that are deferred. When the refactor lands, that block
 * and this `->ignoring()` are deleted together.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Contacts')
    ->ignoring(RequestContactVerificationAction::class);

it('re-imposes every crypto primitive ban on the one exempted class', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../../src/Actions/RequestContactVerificationAction.php');
    $tokens = token_get_all($source);

    /** @var list<string> $called */
    $called = [];

    foreach ($tokens as $index => $token) {
        if (! is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        // Only a real call — a docblock is a comment token and can never reach here.
        $next = $tokens[$index + 1] ?? null;

        if ($next === '(' || (is_array($next) && $next[0] === T_WHITESPACE && ($tokens[$index + 2] ?? null) === '(')) {
            $called[] = $token[1];
        }
    }

    // Guard the guard: a scanner that matched nothing would pass vacuously. The deferred
    // calls must actually be there — the day they go, this fails and tells you to delete
    // the exemption rather than quietly protecting nothing.
    expect($called)->toContain('random_bytes')
        ->and($called)->toContain('random_int');

    $banned = array_diff(ArchPresets::CRYPTO_PRIMITIVES, ['random_bytes', 'random_int']);

    foreach ($banned as $primitive) {
        // NOT `expect($called)->not->toContain($primitive, $message)`: Pest's `toContain`
        // is variadic, so a "message" passed there is silently taken as a second NEEDLE,
        // and the negation then passes whenever the two needles are not both present —
        // i.e. always. Asserted through `in_array` so the message stays a message.
        expect(in_array($primitive, $called, true))->toBeFalse(
            "RequestContactVerificationAction calls {$primitive}(). It is exempt from the crypto preset "
            .'only for the deferred random_bytes()/random_int() refactor; every other primitive still '
            .'belongs in crypto-for-laravel.',
        );
    }
});

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
 * The Dependency Policy as a test. No `alsoAllow`: contacts' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If this goes red, the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../../composer.json');

ArchPresets::noDebuggingLeftovers();
