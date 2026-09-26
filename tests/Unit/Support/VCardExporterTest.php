<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Contacts\Concerns\HasContacts;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\VCardExporter;
use RoundlyConsulting\Contacts\Tests\Fixtures\CustomContactModel;
use RoundlyConsulting\Contacts\Tests\Models\User;

it('emits a line per supported kind', function (): void {
    $user = User::create(['name' => 'Jane Doe']);
    $user->addEmail('jane@example.com', label: 'Work');
    $user->addPhone('+421900000000', label: 'Mobile');
    $user->addUrl('example.com');
    $user->addAddress('12 Main St');

    $vcard = VCardExporter::forOwner($user);

    expect($vcard)->toContain('BEGIN:VCARD')
        ->toContain('VERSION:3.0')
        ->toContain('FN:Jane Doe')
        ->toContain('EMAIL;TYPE=work:jane@example.com')
        ->toContain('TEL;TYPE=cell:+421900000000')
        ->toContain('URL:https://example.com')
        ->toContain('ADR:;;12 Main St;;;;')
        ->toContain('END:VCARD');
});

it('escapes separators and skips empty values', function (): void {
    $contact = new Contact([
        'type' => ContactType::Address,
        'name' => 'X',
        'value' => 'A, B; C',
        'label' => 'Home',
    ]);

    expect($contact->toVCard())->toContain('ADR;TYPE=home:;;A\, B\; C;;;;');

    $empty = new Contact(['type' => ContactType::Email, 'name' => 'X', 'value' => null]);
    expect($empty->toVCard())->not->toContain('EMAIL');
});

it('falls back to a default name', function (): void {
    $contact = new Contact(['type' => ContactType::Custom, 'name' => 'X', 'value' => 'note']);

    expect($contact->toVCard())->toContain('FN:Contact')
        ->toContain('NOTE:note');
});

/**
 * The vCard as content lines: physical lines unfolded (RFC 2425 §5.8.1), split on CRLF.
 *
 * @return list<string>
 */
function vcardLines(string $vcard): array
{
    return explode("\r\n", rtrim(str_replace("\r\n ", '', $vcard), "\r\n"));
}

/** A host model with a structured name and no `name` column. */
final class VCardPerson extends Model
{
    use HasContacts;

    protected $table = 'vcard_people';

    protected $guarded = [];

    public $timestamps = false;
}

function vcardPeopleTable(): void
{
    Schema::create('vcard_people', function (Blueprint $table): void {
        $table->id();
        $table->string('name_prefix')->nullable();
        $table->string('first_name')->nullable();
        $table->string('middle_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('name_suffix')->nullable();
    });
}

it('emits the N property vCard 3.0 requires, from the owner name as a fallback', function (): void {
    $user = User::create(['name' => 'Jane Doe']);
    $user->addEmail('jane@example.com');

    $lines = vcardLines(VCardExporter::forOwner($user));

    expect($lines)->toContain('N:Jane Doe;;;;')
        ->and(array_values(array_filter($lines, fn (string $l): bool => str_starts_with($l, 'N:'))))->toHaveCount(1);
});

it('emits N for a single contact with the default name', function (): void {
    $contact = new Contact(['type' => ContactType::Email, 'name' => 'X', 'value' => 'a@example.com']);

    expect(vcardLines($contact->toVCard()))->toContain('N:Contact;;;;')->toContain('FN:Contact');
});

it('builds N and FN from structured name attributes', function (): void {
    vcardPeopleTable();

    $person = VCardPerson::create([
        'name_prefix' => 'Dr.',
        'first_name' => 'Jana',
        'middle_name' => 'Mária',
        'last_name' => 'Nováková, ml.',
        'name_suffix' => 'PhD',
    ]);
    $person->addEmail('jana@example.com');

    $lines = vcardLines(VCardExporter::forOwner($person->fresh() ?? $person));

    expect($lines)->toContain('N:Nováková\, ml.;Jana;Mária;Dr.;PhD')
        ->toContain('FN:Dr. Jana Mária Nováková\, ml. PhD');
});

it('reads only attributes the owner has, even in strict mode', function (): void {
    vcardPeopleTable();
    Model::preventAccessingMissingAttributes();

    try {
        $person = VCardPerson::query()->findOrFail(VCardPerson::create(['first_name' => 'Ada'])->getKey());

        expect(vcardLines(VCardExporter::forOwner($person)))->toContain('N:;Ada;;;')->toContain('FN:Ada');
    } finally {
        Model::preventAccessingMissingAttributes(false);
    }
});

it('maps a registered label to a TYPE token and never escapes a parameter', function (): void {
    $user = User::create(['name' => 'Jane Doe']);
    $user->addEmail('jane@example.com', label: 'Work');
    $user->addPhone('+421900000000', label: 'Mobile');
    $user->addAddress('12 Main St', label: 'HOME');

    expect(vcardLines(VCardExporter::forOwner($user)))
        ->toContain('EMAIL;TYPE=work:jane@example.com')
        ->toContain('TEL;TYPE=cell:+421900000000')
        ->toContain('ADR;TYPE=home:;;12 Main St;;;;');
});

it('carries a free-text label as a grouped X-ABLabel instead of a TYPE', function (): void {
    $user = User::create(['name' => 'Jane Doe']);
    $user->addPhone('+421900000001', label: 'Office, main');
    $user->addEmail('desk@example.com', label: 'Front desk; "A"');

    $vcard = VCardExporter::forOwner($user);
    $lines = vcardLines($vcard);

    // Parameter values are never backslash-escaped (RFC 2425 §5.8.2).
    foreach ($lines as $line) {
        $head = explode(':', $line, 2)[0];

        expect($head)->not->toContain('\\');
    }

    expect($lines)->toContain('item1.TEL:+421900000001')
        ->toContain('item1.X-ABLabel:Office\, main')
        ->toContain('item2.EMAIL:desk@example.com')
        ->toContain('item2.X-ABLabel:Front desk\; "A"');
});

it('normalises every line break in text to one escaped newline', function (): void {
    $user = User::create(['name' => "Jane\r\nDoe"]);
    $user->addAddress("12 Main St\r\nFlat 3\rBratislava\nSlovakia");

    $vcard = VCardExporter::forOwner($user);

    expect(preg_match('/\r(?!\n)|(?<!\r)\n/', $vcard))->toBe(0)
        ->and(vcardLines($vcard))->toContain('FN:Jane\nDoe')
        ->toContain('ADR:;;12 Main St\nFlat 3\nBratislava\nSlovakia;;;;');
});

it('keeps a url value unescaped, as uri values are not text', function (): void {
    $user = User::create(['name' => 'Jane Doe']);
    $user->addUrl('https://maps.example.com/?q=48.14,17.10;z=12');

    expect(vcardLines(VCardExporter::forOwner($user)))->toContain('URL:https://maps.example.com/?q=48.14,17.10;z=12');
});

it('folds long lines at 75 octets without splitting a UTF-8 sequence', function (): void {
    $address = rtrim(str_repeat('Žltá ruža € 😀 ', 12));
    $user = User::create(['name' => 'Jane Doe']);
    $user->addAddress($address);

    $vcard = VCardExporter::forOwner($user);

    foreach (explode("\r\n", rtrim($vcard, "\r\n")) as $physical) {
        expect(strlen($physical))->toBeLessThanOrEqual(75)
            ->and(mb_check_encoding($physical, 'UTF-8'))->toBeTrue();
    }

    expect(vcardLines($vcard))->toContain('ADR:;;'.$address.';;;;');
});

it('exports through the configured contact model', function (): void {
    config()->set('contacts.model', CustomContactModel::class);
    $user = User::create(['name' => 'Jane Doe']);
    $user->addEmail('jane@example.com');

    $hydrated = [];
    CustomContactModel::retrieved(function (CustomContactModel $contact) use (&$hydrated): void {
        $hydrated[] = $contact->value;
    });

    VCardExporter::forOwner($user);

    expect($hydrated)->toBe(['jane@example.com']);
});
