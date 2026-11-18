<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Infrastructure\Persistence;

use Ocdwefo\VersionService\Domain\Exception\VersionConflictException;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\RetentionLimit;
use Ocdwefo\VersionService\Domain\Model\Version;
use Ocdwefo\VersionService\Domain\Model\VersionHeader;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Override;

/**
 * Versions kept in a PHP array for as long as the process lives. For tests and prototypes.
 */
final class InMemoryVersionRepository implements VersionRepositoryInterface
{
    /** @var array<string, Version> By version id. */
    private array $versions = [];

    #[Override]
    public function add(Version $version): void
    {
        foreach ($this->ofEntity($version->entity) as $stored) {
            if ($stored->number->value === $version->number->value) {
                throw VersionConflictException::numberTaken($version->entity, $version->number);
            }
        }

        $this->versions[$version->id->value] = $version;
    }

    #[Override]
    public function find(VersionId $id): ?Version
    {
        return $this->versions[$id->value] ?? null;
    }

    #[Override]
    public function history(EntityReference $entity, int $limit, int $offset = 0): array
    {
        return array_map(self::header(...), array_slice($this->ofEntity($entity), $offset, $limit));
    }

    #[Override]
    public function count(EntityReference $entity): int
    {
        return count($this->ofEntity($entity));
    }

    #[Override]
    public function nextNumber(EntityReference $entity): VersionNumber
    {
        $latest = $this->ofEntity($entity)[0] ?? null;

        return $latest === null ? VersionNumber::first() : $latest->number->next();
    }

    #[Override]
    public function prune(EntityType $type, RetentionLimit $limit): int
    {
        $entities = [];
        foreach ($this->versions as $version) {
            if ($version->entity->type->value === $type->value) {
                $entities[$version->entity->id->value] = $version->entity;
            }
        }

        $removed = 0;
        foreach ($entities as $entity) {
            $removed += $this->remove(array_slice($this->ofEntity($entity), $limit->value));
        }

        return $removed;
    }

    #[Override]
    public function removeAll(EntityReference $entity): int
    {
        return $this->remove($this->ofEntity($entity));
    }

    /**
     * @param list<Version> $versions
     */
    private function remove(array $versions): int
    {
        foreach ($versions as $version) {
            unset($this->versions[$version->id->value]);
        }

        return count($versions);
    }

    private static function header(Version $version): VersionHeader
    {
        return new VersionHeader(
            $version->id,
            $version->entity,
            $version->number,
            $version->comment,
            $version->author,
            $version->createdAt,
        );
    }

    /**
     * @return list<Version> Newest first.
     */
    private function ofEntity(EntityReference $entity): array
    {
        $versions = array_values(array_filter(
            $this->versions,
            static fn (Version $version): bool => $version->belongsTo($entity),
        ));

        usort($versions, static fn (Version $a, Version $b): int => $b->number->value <=> $a->number->value);

        return $versions;
    }
}
