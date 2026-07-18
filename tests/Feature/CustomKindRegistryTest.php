<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Exceptions\InvalidContactValue;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Rules\ValidContactValue;
use RoundlyConsulting\Contacts\Support\ContactRules;
use RoundlyConsulting\Contacts\Tests\Models\User;

/**
 * The `contacts.types` registry is a documented, host-facing feature: config/contacts.php
 * ships a worked `whatsapp` example, and the README promises a kind outside the six
 * built-ins "takes its label/icon/rules from config('contacts.types.<key>')".
 *
 * Every test below exercises that exact documented example end to end. They exist because
 * the feature was inert: `ContactRules::kinds()` accepted `whatsapp` as a valid type, but
 * the DTO collapsed it to ContactType::Custom and discarded the kind string, so the label
 * resolved to "Other", the icon to "identification", and the host's validation regex never
 * ran at all. Nothing tested the registry against a kind that was actually registered.
 */
beforeEach(function (): void {
    // The worked example from config/contacts.php, verbatim.
    config()->set('contacts.types', [
        'whatsapp' => [
            'label' => 'WhatsApp',
            'icon' => 'chat-bubble',
            'rules' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],
        ],
    ]);
});

it('keeps the registered kind on the DTO instead of discarding it', function (): void {
    $data = ContactData::fromArray(['type' => 'whatsapp', 'value' => '+421900123456']);

    // The enum still collapses to Custom — `whatsapp` is not a built-in case, and that
    // contract is unchanged. What must survive is the raw kind alongside it.
    expect($data->type)->toBe(ContactType::Custom)
        ->and($data->kind)->toBe('whatsapp');
});

it('resolves the registered label and icon rather than the Custom fallbacks', function (): void {
    $data = ContactData::fromArray(['type' => 'whatsapp', 'value' => '+421900123456']);

    expect($data->kindLabel())->toBe('WhatsApp')
        ->and($data->kindIcon())->toBe('chat-bubble');
});

it('runs the host regex registered for the kind', function (): void {
    $data = ContactData::fromArray(['type' => 'whatsapp', 'value' => 'not-a-number']);

    // The whole point of registering rules: this value passes `required|string` (the Custom
    // default) and must be rejected by the registered regex.
    expect($data->validationRules())->toContain('regex:/^\+?[1-9]\d{6,14}$/')
        ->and(Validator::make(['value' => 'not-a-number'], ['value' => $data->validationRules()])->passes())
        ->toBeFalse();
});

it('accepts a value that satisfies the registered regex', function (): void {
    $data = ContactData::fromArray(['type' => 'whatsapp', 'value' => '+421900123456']);

    expect(Validator::make(['value' => $data->value], ['value' => $data->validationRules()])->passes())
        ->toBeTrue();
});

it('persists and reads back the registered kind through the add flow', function (): void {
    $user = User::create();

    $contact = $user->addContact(ContactData::fromArray([
        'type' => 'whatsapp',
        'value' => '+421900123456',
        'name' => 'Support',
    ]));

    expect($contact->kind)->toBe('whatsapp')
        ->and($contact->type)->toBe(ContactType::Custom)
        ->and($contact->kindLabel())->toBe('WhatsApp')
        ->and($contact->kindIcon())->toBe('chat-bubble');

    // And it round-trips out of the database, not just off the in-memory model. This is
    // where the native enum cast used to raise a ValueError on a registered custom kind.
    $fresh = Contact::query()->findOrFail($contact->getKey());

    expect($fresh->kind)->toBe('whatsapp')
        ->and($fresh->type)->toBe(ContactType::Custom)
        ->and($fresh->kindLabel())->toBe('WhatsApp');
});

it('scopes queries by the registered kind', function (): void {
    $user = User::create();

    $user->addContact(ContactData::fromArray(['type' => 'whatsapp', 'value' => '+421900123456', 'name' => 'W']));
    $user->addContact(ContactData::fromArray(['type' => 'custom', 'value' => 'plain', 'name' => 'C']));

    expect(Contact::query()->ofType('whatsapp')->count())->toBe(1)
        ->and(Contact::query()->ofType('custom')->count())->toBe(1);
});

it('rejects a value the registered regex refuses, through the add flow', function (): void {
    $user = User::create();

    expect(fn () => $user->addContact(ContactData::fromArray([
        'type' => 'whatsapp',
        'value' => 'not-a-number',
        'name' => 'Bad',
    ])))->toThrow(InvalidContactValue::class);
});

it('still treats an unregistered kind as a plain Custom contact', function (): void {
    $data = ContactData::fromArray(['type' => 'telegram', 'value' => 'handle']);

    // Unregistered: kind is preserved, but with no config entry the Custom fallbacks apply.
    expect($data->type)->toBe(ContactType::Custom)
        ->and($data->kind)->toBe('telegram')
        ->and($data->kindLabel())->toBe('Other')
        ->and($data->kindIcon())->toBe('identification');
});

it('leaves the built-in kinds untouched', function (): void {
    $data = ContactData::fromArray(['type' => 'email', 'value' => 'A@Example.com']);

    expect($data->type)->toBe(ContactType::Email)
        ->and($data->kind)->toBe('email')
        ->and($data->kindLabel())->toBe('Email')
        ->and($data->normalized()->value)->toBe('a@example.com');
});

it('validates a registered kind through the reusable rule object', function (): void {
    // ValidContactValue is what a host drops into a FormRequest, so the registry has to
    // reach it too — not just the DTO.
    $rule = new ValidContactValue(ContactType::Custom, 'whatsapp');

    $failed = false;
    $rule->validate('value', 'not-a-number', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('offers the registered kind as an accepted type in the array rules', function (): void {
    expect(ContactRules::kinds())->toContain('whatsapp');
});

/**
 * Regression, and the reason `type` is an accessor rather than a cast.
 *
 * Laravel caches whatever a CastsAttributes `get()` returns and feeds it back through
 * `set()` on every save (`mergeAttributesFromClassCasts`). Because `get()` must return a
 * ContactType and `whatsapp` types as Custom, that round-trip rewrote the column to
 * `custom` — so merely READING `$contact->type` before a save silently destroyed the kind.
 * The same trap applies to an `Attribute` accessor with the default object caching on.
 *
 * This test reads `type` first, then saves, which is exactly the order that lost the kind.
 */
it('keeps the raw kind when type is read before the model is saved', function (): void {
    $user = User::create();

    $contact = $user->addContact(ContactData::fromArray([
        'type' => 'whatsapp',
        'value' => '+421900123456',
        'name' => 'Support',
    ]));

    // Read the enum — this is what populated the cast cache and poisoned the write.
    expect($contact->type)->toBe(ContactType::Custom);

    $contact->label = 'Support line';
    $contact->save();

    expect($contact->fresh()->kind)->toBe('whatsapp')
        ->and(DB::table('contacts')->where('id', $contact->getKey())->value('type'))->toBe('whatsapp');
});

it('keeps each registered kind primary independently', function (): void {
    $user = User::create();

    // Both type as Custom. Demoting on the enum would let the second demote the first.
    $whatsapp = $user->addContact(ContactData::fromArray(['type' => 'whatsapp', 'value' => '+421900123456', 'name' => 'W']));
    $telegram = $user->addContact(ContactData::fromArray(['type' => 'telegram', 'value' => 'handle', 'name' => 'T']));

    expect($whatsapp->fresh()->is_primary)->toBeTrue()
        ->and($telegram->fresh()->is_primary)->toBeTrue();
});
