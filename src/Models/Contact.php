<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Contacts\Database\Factories\ContactFactory;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Support\VCardExporter;

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
 * @property Collection<array-key, mixed>|null $meta
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Model|null $owner
 */
final class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

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
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('value', 'like', $like)
                ->orWhere('label', 'like', $like);
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

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        $configured = config('contacts.table');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return parent::getTable();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'is_primary' => 'boolean',
            'position' => 'integer',
            'verified_at' => 'datetime',
            'meta' => 'collection',
        ];
    }
}
