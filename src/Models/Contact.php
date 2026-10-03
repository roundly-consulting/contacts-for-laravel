<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Contracts\Addressable;
use RoundlyConsulting\Addresses\Traits\HasAddresses;
use RoundlyConsulting\Connections\Concerns\HasConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Contacts\Concerns\HasContactRelations;
use RoundlyConsulting\Contacts\ContactsManager;
use RoundlyConsulting\Contacts\Database\Factories\ContactFactory;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Support\ContactKind;
use RoundlyConsulting\Contacts\Support\ContactsConfig;
use RoundlyConsulting\Contacts\Support\KindGroup;
use RoundlyConsulting\Contacts\Support\VCardExporter;
use SensitiveParameter;

/**
 * @property int $id
 * @property int|null $owner_id
 * @property string|null $owner_type
 * @property ContactType $type
 * @property string $name
 * @property string|null $value
 * @property string|null $label
 * @property string|null $category
 * @property bool $is_primary
 * @property int $position
 * @property CarbonInterface|null $verified_at
 * @property string|null $verification_token
 * @property CarbonInterface|null $verification_expires_at
 * @property int $verification_attempts
 * @property Collection<array-key, mixed>|null $meta
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read string $kind
 * @property-read Model|null $owner
 *
 * Not final: `contacts.model` documents extending this model in a host app.
 */
class Contact extends Model implements Addressable, Connectable
{
    use HasAddresses;
    use HasConnections;
    use HasContactRelations;

    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['verification_token'];

    /**
     * The deletion time a restore() in progress is undoing — how it finds the addresses
     * and connections that were trashed together with this contact.
     */
    private ?CarbonInterface $restoringFrom = null;

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeForOwner(Builder $query, Model $owner, bool $includeAlsoWithoutOwner = false): Builder
    {
        return $query->where(function (Builder $query) use ($owner, $includeAlsoWithoutOwner): void {
            $query->whereMorphedTo('owner', $owner);

            if ($includeAlsoWithoutOwner) {
                $query->orWhere(fn (Builder $q): Builder => $q->withoutOwner());
            }
        });
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeWithoutOwner(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q
            ->whereNull('owner_id')
            ->whereNull('owner_type'));
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeWithoutCategory(Builder $query): Builder
    {
        return $query->whereNull('category');
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeOfType(Builder $query, ContactType|string $type): Builder
    {
        $value = $type instanceof ContactType ? $type->value : $type;

        return $query->where('type', $value);
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeVerified(Builder $query, bool $verified = true): Builder
    {
        return $verified
            ? $query->whereNotNull('verified_at')
            : $query->whereNull('verified_at');
    }

    /**
     * Contacts that are unverified but currently hold a verification token.
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopePendingVerification(Builder $query): Builder
    {
        return $query->whereNull('verified_at')
            ->whereNotNull('verification_token');
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->whereLikeEscaped('name', $term)
                ->whereLikeEscaped('value', $term, 'or')
                ->whereLikeEscaped('label', $term, 'or');
        });
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * Render this contact as a minimal vCard 3.0 string.
     */
    public function toVCard(): string
    {
        return VCardExporter::forContacts([$this]);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * `type` reads as a ContactType but is STORED as the raw kind.
     *
     * Neither of the obvious mechanisms can express that:
     *
     * - The **native enum cast** calls `ContactType::from()` and raises a ValueError for
     *   any host-registered custom kind (`"whatsapp" is not a valid backing value`), so a
     *   row the registry is designed to support cannot even be read back.
     * - A **CastsAttributes class** loses the kind on write. Laravel caches whatever `get()`
     *   returns in `classCastCache` and `mergeAttributesFromClassCasts()` feeds it back
     *   through `set()` on every save. Since `get()` must return a ContactType, and
     *   `whatsapp` types as Custom, that round-trip rewrites the column to `custom`. Merely
     *   READING `$contact->type` before saving was enough to destroy the kind — silently.
     *
     * An accessor with object caching off has no such round-trip: the enum is computed per
     * read and the stored attribute is never written back from it. `withoutObjectCaching()`
     * is load-bearing — Attribute caches object returns by default, which reintroduces the
     * exact bug above.
     *
     * @return Attribute<ContactType, string>
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): ContactType => ContactType::fromValueOrCustom(
                is_string($value) ? $value : null,
            ),
            set: static fn (ContactType|string|null $value): string => match (true) {
                $value instanceof ContactType => $value->value,
                is_string($value) && $value !== '' => $value,
                default => ContactType::Custom->value,
            },
        )->withoutObjectCaching();
    }

    /**
     * The raw kind this contact is stored under — a built-in ContactType value, or a custom
     * kind registered in `config('contacts.types')` (e.g. `whatsapp`).
     *
     * `$contact->type` collapses anything outside the six built-ins to Custom; this is the
     * string the registry is actually keyed by, so it is what resolves the label, icon and
     * validation rules a host registered.
     *
     * @return Attribute<non-empty-string, never>
     */
    protected function kind(): Attribute
    {
        return Attribute::get(function (): string {
            $raw = $this->attributes['type'] ?? null;

            return is_string($raw) && $raw !== '' ? $raw : ContactType::Custom->value;
        })->withoutObjectCaching();
    }

    /**
     * Human label for this contact's kind — the registered one for a custom kind, the
     * type's own otherwise. Distinct from `$contact->label`, the host's free-text label for
     * this particular contact (e.g. "Work").
     */
    public function kindLabel(): string
    {
        return ContactKind::label($this->type, $this->kind);
    }

    /**
     * Icon name for this contact's kind.
     */
    public function kindIcon(): string
    {
        return ContactKind::icon($this->type, $this->kind);
    }

    /**
     * A single-line render of this contact's primary structured address, falling
     * back to the loose `value` when no structured address is attached. Keeps
     * pre-integration rows (loose value, no Address) rendering correctly.
     */
    public function formattedAddress(): ?string
    {
        $address = $this->primaryAddress();

        if ($address !== null) {
            $formatted = $address->formatted();

            if ($formatted !== '') {
                return $formatted;
            }
        }

        return $this->value;
    }

    /**
     * Generate a verification token, persist its hash, and fire
     * ContactVerificationRequested with the plaintext for the host to deliver.
     */
    public function requestVerification(): string
    {
        return app(ContactsManager::class)->verification()->request($this);
    }

    /**
     * Confirm the contact against a plaintext token, marking it verified.
     */
    public function confirmVerification(#[SensitiveParameter] string $token): Contact
    {
        return app(ContactsManager::class)->verification()->confirm($this, $token);
    }

    protected static function booted(): void
    {
        // A verification proves ownership of ONE value of ONE kind. Changing either —
        // through the actions or a low-level write — drops it and voids the pending
        // token, so a verified address can't be swapped for another and stay verified,
        // and a token sent to the old value can't confirm the new one. A write that sets
        // the verification fields itself (e.g. an import) is taken at its word.
        self::updating(static function (Contact $contact): void {
            if (! $contact->isDirty(['value', 'type'])) {
                return;
            }

            if (! $contact->isDirty('verified_at')) {
                $contact->verified_at = null;
            }

            if (! $contact->isDirty('verification_token')) {
                $contact->verification_token = null;
                $contact->verification_expires_at = null;
                $contact->verification_attempts = 0;
            }
        });

        // A restored primary must not become a second one: if its kind promoted another
        // contact meanwhile, it comes back as a secondary.
        self::restoring(static function (Contact $contact): void {
            $contact->restoringFrom = $contact->deleted_at;

            if ($contact->is_primary && KindGroup::hasOtherPrimary($contact)) {
                $contact->is_primary = false;
            }
        });

        // Soft-deleting an already trashed contact is a no-op: re-stamping its deleted_at
        // would lose the stamp restore() finds its addresses and connections by.
        self::deleting(static fn (Contact $contact): ?bool => $contact->trashed() && ! $contact->isForceDeleting() ? false : null);

        // The structured address book and the relationship edges exist only for this
        // contact, so they follow it: trashed with a soft delete (and back with a restore),
        // gone with a force delete.
        self::deleted(static function (Contact $contact): void {
            if ($contact->isForceDeleting()) {
                foreach ($contact->dependents() as $relation) {
                    $relation->withTrashed()->get()->each(static fn (Address|Connection $child): ?bool => $child->forceDelete());
                }

                return;
            }

            $trashedAt = $contact->deleted_at;

            foreach ($contact->dependents() as $relation) {
                $relation->get()->each(static function (Address|Connection $child) use ($trashedAt): void {
                    $child->delete();

                    // Stamped with the contact's own deletion time: that shared stamp is how
                    // restore() tells what went down with the contact from what a host had
                    // deleted on its own before.
                    $child->forceFill([$child->getDeletedAtColumn() => $trashedAt])->saveQuietly();
                });
            }
        });

        self::restored(static function (Contact $contact): void {
            $trashedAt = $contact->restoringFrom;
            $contact->restoringFrom = null;

            if ($trashedAt === null) {
                return;
            }

            foreach ($contact->dependents() as $relation) {
                $related = $relation->getRelated();

                $relation->onlyTrashed()
                    ->where($related->getDeletedAtColumn(), $related->fromDateTime($trashedAt))
                    ->get()
                    ->each(static fn (Address|Connection $child): bool => $child->restore());
            }
        });
    }

    /**
     * The rows that exist only for this contact: its structured address book and its
     * relationship edges in both directions.
     *
     * @return list<MorphMany<Address, $this>|MorphMany<Connection, $this>>
     */
    private function dependents(): array
    {
        return [$this->addresses(), $this->connections(), $this->connectors()];
    }

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        return ContactsConfig::table() ?? parent::getTable();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // `type` is deliberately absent — see the type() accessor for why neither the
            // native enum cast nor a CastsAttributes class can express it.
            'is_primary' => 'boolean',
            'position' => 'integer',
            'verified_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'verification_attempts' => 'integer',
            'meta' => 'collection',
        ];
    }
}
