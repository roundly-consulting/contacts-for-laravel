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

/**
 * @property int $id
 * @property int|null $owner_id
 * @property string|null $owner_type
 * @property string $name
 * @property string|null $value
 * @property string|null $category
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

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
        ];
    }
}
