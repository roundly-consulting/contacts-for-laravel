<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Contacts\Actions\AddContactAction;
use RoundlyConsulting\Contacts\ContactBook;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\ContactVerification;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Events\ContactVerificationRequested;
use RoundlyConsulting\Contacts\Facades\Contacts;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('returns a contact book and a verification accessor', function (): void {
    expect(Contacts::for(User::create()))->toBeInstanceOf(ContactBook::class)
        ->and(Contacts::verification())->toBeInstanceOf(ContactVerification::class);
});

it('adds a contact through the fluent builder', function (): void {
    $user = User::create();

    $contact = Contacts::for($user)
        ->phone('+421 900 000 000')
        ->label('Mobile')
        ->primary()
        ->add();

    expect($contact->value)->toBe('+421900000000')
        ->and($contact->type)->toBe(ContactType::Phone)
        ->and($contact->is_primary)->toBeTrue()
        ->and($contact->owner->is($user))->toBeTrue();
});

it('starts a builder from every book entry point', function (): void {
    $user = User::create();
    $book = Contacts::for($user);

    $email = $book->email('A@B.com')->add();
    $url = $book->url('example.com')->add();
    $address = $book->address('12 Main St')->add();
    $social = $book->type('social')->value('@handle')->add();

    expect($email->value)->toBe('a@b.com')
        ->and($url->value)->toBe('https://example.com')
        ->and($address->type)->toBe(ContactType::Address)
        ->and($social->type)->toBe(ContactType::Social);
});

it('adds a structured address contact straight from the book', function (): void {
    $user = User::create();

    $contact = Contacts::for($user)->structuredAddress([
        'city' => 'Vienna',
        'street' => 'Ring 3',
        'postalCode' => '1010',
        'countryIso' => 'AT',
    ])->add();

    expect($contact->type)->toBe(ContactType::Address)
        ->and($contact->addresses()->count())->toBe(1)
        ->and($contact->value)->toContain('Ring 3');
});

it('adds a contact from data', function (): void {
    $user = User::create();

    $contact = Contacts::for($user)->add(new ContactData(ContactType::Email, 'a@b.com'));

    expect($contact->value)->toBe('a@b.com')
        ->and($contact->exists)->toBeTrue();
});

it('syncs an owner\'s contacts of one kind', function (): void {
    $user = User::create();
    $user->addPhone('+421900000009');

    $result = Contacts::for($user)->sync(ContactType::Phone, [
        new ContactData(ContactType::Phone, '+421900000001'),
        new ContactData(ContactType::Phone, '+421900000002'),
    ]);

    expect($result)->toHaveCount(2)
        ->and($user->contacts()->pluck('value')->all())->toBe(['+421900000001', '+421900000002']);
});

it('reads an owner\'s contacts, by kind and primary', function (): void {
    $user = User::create();
    $other = User::create();
    $first = $user->addEmail('a@b.com');
    $user->addEmail('c@d.com');
    $user->addPhone('+421900000001');
    $other->addEmail('x@y.com');

    $book = Contacts::for($user);

    expect($book->all())->toHaveCount(3)
        ->and($book->ofType(ContactType::Email)->pluck('value')->all())->toBe(['a@b.com', 'c@d.com'])
        ->and($book->ofType('phone'))->toHaveCount(1)
        ->and($book->primary(ContactType::Email)?->is($first))->toBeTrue()
        ->and($book->primary(ContactType::Url))->toBeNull();
});

it('exports a vcard of the owner\'s contacts', function (): void {
    $user = User::create(['name' => 'Jane Doe']);
    $user->addEmail('jane@example.com', label: 'Work');

    expect(Contacts::for($user)->vCard())->toContain('BEGIN:VCARD')
        ->toContain('FN:Jane Doe')
        ->toContain('EMAIL;TYPE=work:jane@example.com');
});

it('updates, promotes and deletes a contact', function (): void {
    $user = User::create();
    $contact = $user->addEmail('a@b.com');
    $other = $user->addEmail('c@d.com');

    $updated = Contacts::update($contact, new ContactData(ContactType::Email, 'new@b.com'));
    expect($updated->value)->toBe('new@b.com');

    Contacts::setPrimary($other);
    expect($other->fresh()->is_primary)->toBeTrue()
        ->and($contact->fresh()->is_primary)->toBeFalse();

    Contacts::delete($contact);
    expect($user->contacts()->count())->toBe(1);
});

it('runs the verification flow through the accessor', function (): void {
    Event::fake([ContactVerificationRequested::class]);
    $contact = User::create()->addEmail('a@b.com');

    $token = Contacts::verification()->request($contact);

    Event::assertDispatched(ContactVerificationRequested::class);
    expect($contact->fresh()->verification_token)->not->toBeNull();

    $confirmed = Contacts::verification()->confirm($contact->fresh(), $token);

    expect($confirmed->isVerified())->toBeTrue()
        ->and($confirmed->verification_token)->toBeNull();
});

it('marks a contact verified without a token', function (): void {
    $contact = User::create()->addEmail('a@b.com');
    $at = Carbon::parse('2026-01-02 03:04:05');

    Contacts::verification()->markVerified($contact, $at);

    expect($contact->fresh()->verified_at->equalTo($at))->toBeTrue();
});

it('lists the contacts shared with a connectable owner', function (): void {
    expect(Contacts::sharedWith(Contact::factory()->create()))->toHaveCount(0);
});

it('exposes validation rules for a contacts array', function (): void {
    expect(Contacts::validationRules('people'))->toHaveKey('people.*.value');
});

it('serves the same api from an injected manager', function (): void {
    $user = User::create();
    $manager = app(ContactsManager::class);

    $contact = $manager->for($user)->email('di@example.com')->add();
    $manager->verification()->markVerified($contact);

    expect($manager)->toBe(Contacts::getFacadeRoot())
        ->and($contact->fresh()->isVerified())->toBeTrue();
});

it('lets the raw action add a contact', function (): void {
    $user = User::create();

    $contact = app(AddContactAction::class)->execute($user, new ContactData(ContactType::Email, 'raw@example.com'));

    expect($user->primaryEmail()?->is($contact))->toBeTrue();
});
