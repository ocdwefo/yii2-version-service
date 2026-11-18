<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Repository;

use Ocdwefo\VersionService\Domain\Exception\VersionConflictException;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\RetentionLimit;
use Ocdwefo\VersionService\Domain\Model\Version;
use Ocdwefo\VersionService\Domain\Model\VersionHeader;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;

/**
 * Storage of versions (port). Versions are immutable, so there is no update.
 *
 * The package ships an in-memory and a yii\db implementation; a project may write its own
 * and check it against the contract test case of the package.
 */
interface VersionRepositoryInterface
{
    /**
     * @throws VersionConflictException When the entity already has a version with this number.
     */
    public function add(Version $version): void;

    public function find(VersionId $id): ?Version;

    /**
     * Versions of the entity without their snapshots, newest first: the latest version is the first item.
     * Snapshots can be large; a history list does not need them, so they are not loaded.
     *
     * @param positive-int $limit
     * @param non-negative-int $offset
     *
     * @return list<VersionHeader>
     */
    public function history(EntityReference $entity, int $limit, int $offset = 0): array;

    public function count(EntityReference $entity): int;

    /**
     * Number the next version of the entity gets: the highest stored number + 1, or 1.
     */
    public function nextNumber(EntityReference $entity): VersionNumber;

    /**
     * Removes the versions beyond the limit for every entity of the type: each entity keeps its newest
     * versions, as many as the limit says. Entities of other types are not touched.
     *
     * @return int Number of removed versions.
     */
    public function prune(EntityType $type, RetentionLimit $limit): int;

    /**
     * Removes the whole history of the entity, e.g. after the entity itself is deleted.
     *
     * @return int Number of removed versions.
     */
    public function removeAll(EntityReference $entity): int;
}
