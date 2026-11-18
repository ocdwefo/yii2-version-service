<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Infrastructure\Yii2;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Exception\VersionConflictException;
use Ocdwefo\VersionService\Domain\Model\AuthorId;
use Ocdwefo\VersionService\Domain\Model\Comment;
use Ocdwefo\VersionService\Domain\Model\EntityId;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\RetentionLimit;
use Ocdwefo\VersionService\Domain\Model\Snapshot;
use Ocdwefo\VersionService\Domain\Model\Version;
use Ocdwefo\VersionService\Domain\Model\VersionHeader;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Override;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\IntegrityException;
use yii\db\Query;
use yii\di\Instance;

/**
 * Versions in an SQL table, through the yii\db query builder (no ActiveRecord): MySQL, PostgreSQL, SQLite.
 * The table comes from {@see Migration\M261117000000CreateVersionTable}.
 *
 * Times are stored in UTC with microseconds, snapshots as JSON.
 */
final readonly class DbVersionRepository implements VersionRepositoryInterface
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s.u';

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION;

    private Connection $db;

    private DateTimeZone $utc;

    /**
     * @param Connection|string $db The connection or its application component id.
     */
    public function __construct(
        Connection|string $db = 'db',
        private string $table = '{{%version}}',
    ) {
        $connection = Instance::ensure($db, Connection::class);
        if (!$connection instanceof Connection) {
            throw new InvalidConfigException(sprintf('"%s" is not a %s.', is_string($db) ? $db : $db::class, Connection::class));
        }

        $this->db = $connection;
        $this->utc = new DateTimeZone('UTC');
    }

    #[Override]
    public function add(Version $version): void
    {
        try {
            $this->db->createCommand()->insert($this->table, [
                'id' => $version->id->value,
                'entity_type' => $version->entity->type->value,
                'entity_id' => $version->entity->id->value,
                'number' => $version->number->value,
                // Bound as is: for a JSON column yii\db would encode the string once more (JsonExpression),
                // and without our flags 2.0 would come back as 2.
                'snapshot' => new Expression(':snapshot', [':snapshot' => json_encode($version->snapshot->data, self::JSON_FLAGS)]),
                'comment' => $version->comment->value,
                'author_id' => $version->author->value,
                'created_at' => $version->createdAt->setTimezone($this->utc)->format(self::DATE_FORMAT),
            ])->execute();
        } catch (IntegrityException $exception) {
            throw VersionConflictException::numberTaken($version->entity, $version->number, $exception);
        }
    }

    #[Override]
    public function find(VersionId $id): ?Version
    {
        $row = (new Query())->from($this->table)->where(['id' => $id->value])->one($this->db);

        return $row === false ? null : $this->hydrate(self::row($row));
    }

    #[Override]
    public function history(EntityReference $entity, int $limit, int $offset = 0): array
    {
        $rows = $this->ofEntity($entity)
            ->select(['id', 'entity_type', 'entity_id', 'number', 'comment', 'author_id', 'created_at']) // no snapshot
            ->orderBy(['number' => SORT_DESC])
            ->limit($limit)
            ->offset($offset)
            ->all($this->db);

        return array_values(array_map(fn (mixed $row): VersionHeader => $this->hydrateHeader(self::row($row)), $rows));
    }

    #[Override]
    public function count(EntityReference $entity): int
    {
        return self::toInt($this->ofEntity($entity)->count('*', $this->db), 'count');
    }

    #[Override]
    public function nextNumber(EntityReference $entity): VersionNumber
    {
        $max = $this->ofEntity($entity)->max('number', $this->db);

        return $max === null ? VersionNumber::first() : VersionNumber::fromInt(self::toInt($max, 'number'))->next();
    }

    /**
     * Portable: one query finds the entities over the limit, then two statements per entity find the number
     * of the oldest version to keep and delete the older ones.
     */
    #[Override]
    public function prune(EntityType $type, RetentionLimit $limit): int
    {
        $removed = 0;
        foreach ($this->entitiesExceeding($type, $limit) as $entity) {
            $removed += $this->pruneEntity($entity, $limit);
        }

        return $removed;
    }

    #[Override]
    public function removeAll(EntityReference $entity): int
    {
        return $this->db->createCommand()->delete($this->table, $this->entityCondition($entity))->execute();
    }

    /**
     * Materialized, so pruning while iterating does not disturb the query.
     *
     * @return list<EntityReference>
     */
    private function entitiesExceeding(EntityType $type, RetentionLimit $limit): array
    {
        $ids = (new Query())
            ->select('entity_id')
            ->from($this->table)
            ->where(['entity_type' => $type->value])
            ->groupBy('entity_id')
            ->having(['>', 'COUNT(*)', $limit->value])
            ->orderBy(['entity_id' => SORT_ASC])
            ->column($this->db);

        return array_values(array_map(
            static fn (mixed $id): EntityReference => new EntityReference($type, EntityId::fromInt(self::toInt($id, 'entity_id'))),
            $ids,
        ));
    }

    private function pruneEntity(EntityReference $entity, RetentionLimit $limit): int
    {
        $oldestKept = $this->ofEntity($entity)
            ->select('number')
            ->orderBy(['number' => SORT_DESC])
            ->offset($limit->value - 1)
            ->limit(1)
            ->scalar($this->db);

        if ($oldestKept === false || $oldestKept === null) {
            return 0; // fewer versions than the limit
        }

        return $this->db->createCommand()->delete($this->table, [
            'and',
            $this->entityCondition($entity),
            ['<', 'number', self::toInt($oldestKept, 'number')],
        ])->execute();
    }

    private function ofEntity(EntityReference $entity): Query
    {
        return (new Query())->from($this->table)->where($this->entityCondition($entity));
    }

    /**
     * @return array{entity_type: int, entity_id: int}
     */
    private function entityCondition(EntityReference $entity): array
    {
        return ['entity_type' => $entity->type->value, 'entity_id' => $entity->id->value];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function hydrate(array $row): Version
    {
        $header = $this->hydrateHeader($row);

        return new Version(
            $header->id,
            $header->entity,
            $header->number,
            $this->snapshot(self::toString($row['snapshot'] ?? null, 'snapshot')),
            $header->comment,
            $header->author,
            $header->createdAt,
        );
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function hydrateHeader(array $row): VersionHeader
    {
        return new VersionHeader(
            VersionId::fromString(self::toString($row['id'] ?? null, 'id')),
            new EntityReference(
                EntityType::fromInt(self::toInt($row['entity_type'] ?? null, 'entity_type')),
                EntityId::fromInt(self::toInt($row['entity_id'] ?? null, 'entity_id')),
            ),
            VersionNumber::fromInt(self::toInt($row['number'] ?? null, 'number')),
            Comment::fromString(self::toString($row['comment'] ?? null, 'comment')),
            AuthorId::fromInt(self::toInt($row['author_id'] ?? null, 'author_id')),
            new DateTimeImmutable(self::toString($row['created_at'] ?? null, 'created_at'), $this->utc),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function row(mixed $row): array
    {
        if (!is_array($row)) {
            throw new InvalidValueException('Version row must be an array.');
        }

        return $row;
    }

    private function snapshot(string $json): Snapshot
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidValueException('Stored snapshot is not valid JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($data)) {
            throw new InvalidValueException('Stored snapshot must be a JSON object.');
        }

        return Snapshot::fromArray($data);
    }

    /**
     * Drivers return integers as int or, depending on the driver settings, as numeric string.
     * filter_var also rejects a string out of the int range instead of capping it silently.
     */
    private static function toInt(mixed $value, string $column): int
    {
        $int = is_int($value) || is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
        if ($int !== false) {
            return $int;
        }

        throw new InvalidValueException(sprintf('Column "%s" must hold an integer, %s given.', $column, get_debug_type($value)));
    }

    private static function toString(mixed $value, string $column): string
    {
        if (!is_string($value)) {
            throw new InvalidValueException(sprintf('Column "%s" must hold a string, %s given.', $column, get_debug_type($value)));
        }

        return $value;
    }
}
