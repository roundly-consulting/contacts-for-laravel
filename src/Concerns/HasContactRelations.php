<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Contacts\Exceptions\RelationshipException;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactModel;
use RoundlyConsulting\Contacts\Support\ContactsConfig;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Typed, CRM-flavoured sugar over the connections graph. Maps a free-form
 * relationship "kind" (works_at, spouse_of, reports_to, …) onto a connection's
 * meta, validated against config('contacts.relationship_kinds') when set.
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements Connectable
 */
trait HasContactRelations
{
    /**
     * Relate this contact to another connectable under a named kind. The kind is
     * stored in the connection's meta and merged over any existing meta.
     *
     * @param  array<string, mixed>  $meta
     */
    public function relateTo(Connectable $other, string $kind, array $meta = []): Connection
    {
        self::assertRelationshipKind($kind);

        if ($this->isSameConnectable($other)) {
            throw RelationshipException::toSelf();
        }

        return $this->connectTo($other, null, null, [...$meta, 'kind' => $kind]);
    }

    /**
     * The contacts this contact is related to under the given kind. Like the connections
     * listing helpers (`connectablesOfType()`), only active relationships count while
     * `connections.enforce_active_on_check` is on: a blocked, pending or expired one is left out.
     *
     * @return EloquentCollection<int, Contact>
     */
    public function relationsOfKind(string $kind): EloquentCollection
    {
        $model = ContactModel::class();

        $type = (new $model)->getMorphClass();

        /** @var EloquentCollection<int, Connection> $connections */
        $connections = $this->connections()
            ->where('connectable_type', $type)
            ->when(
                Config::boolean('connections.enforce_active_on_check', true),
                static fn (Builder $query): Builder => $query->active(),
            )
            ->with('connectable')
            ->get();

        /** @var EloquentCollection<int, Contact> $related */
        $related = $connections
            ->filter(static fn (Connection $connection): bool => $connection->meta('kind') === $kind)
            ->map(static fn (Connection $connection): ?Model => $connection->connectable)
            ->filter(static fn (?Model $model): bool => $model instanceof Contact)
            ->values();

        return $related;
    }

    /**
     * Validate a kind against the configured allow-list, if one is set. An empty
     * list keeps kinds free-form.
     *
     * @throws RelationshipException
     */
    private static function assertRelationshipKind(string $kind): void
    {
        $configured = ContactsConfig::relationshipKinds();

        if ($configured === []) {
            return;
        }

        // Accept either a list of kinds or a kind => label map.
        if (array_key_exists($kind, $configured) || in_array($kind, $configured, true)) {
            return;
        }

        throw RelationshipException::unknownKind($kind);
    }

    private function isSameConnectable(Connectable $other): bool
    {
        return $other->getMorphClass() === $this->getMorphClass()
            && (string) $other->getKey() === (string) $this->getKey();
    }
}
