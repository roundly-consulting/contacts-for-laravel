<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\Contacts\Tests\Models\CustomContact;
use RoundlyConsulting\Contacts\Tests\Models\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(ContactModel::class())->toBe(Contact::class);
});

it('resolves a host model extending the packaged one', function (): void {
    config()->set('contacts.model', CustomContact::class);

    expect(ContactModel::class())->toBe(CustomContact::class);
});

it('refuses a foreign model instead of falling back to the packaged one', function (): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set('contacts.model', User::class);

    expect(fn (): string => ContactModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [contacts.model] must be a class-string of ['.Contact::class.'], ['.User::class.'] given.',
    );
});

it('throws when the configured model is not an eloquent model', function (): void {
    config()->set('contacts.model', 'NotAModel');

    ContactModel::class();
})->throws(InvalidConfigurationException::class);
