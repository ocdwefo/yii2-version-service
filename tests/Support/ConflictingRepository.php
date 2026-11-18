<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support;

use Ocdwefo\VersionService\Domain\Exception\VersionConflictException;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\RetentionLimit;
use Ocdwefo\VersionService\Domain\Model\Version;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Infrastructure\Persistence\InMemoryVersionRepository;
use Override;

/**
 * Pretends concurrent processes take the version number for the first $conflicts writes.
 */
final class ConflictingRepository implements VersionRepositoryInterface
{
    public int $attempts = 0;

    public function __construct(
        private int $conflicts,
        private readonly InMemoryVersionRepository $inner = new InMemoryVersionRepository(),
    ) {
    }

    #[Override]
    public function add(Version $version): void
    {
        $this->attempts++;
        if ($this->conflicts > 0) {
            $this->conflicts--;

            throw VersionConflictException::numberTaken($version->entity, $version->number);
        }

        $this->inner->add($version);
    }

    #[Override]
    public function find(VersionId $id): ?Version
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function history(EntityReference $entity, int $limit, int $offset = 0): array
    {
        return $this->inner->history($entity, $limit, $offset);
    }

    #[Override]
    public function count(EntityReference $entity): int
    {
        return $this->inner->count($entity);
    }

    #[Override]
    public function nextNumber(EntityReference $entity): VersionNumber
    {
        return $this->inner->nextNumber($entity);
    }

    #[Override]
    public function prune(EntityType $type, RetentionLimit $limit): int
    {
        return $this->inner->prune($type, $limit);
    }

    #[Override]
    public function removeAll(EntityReference $entity): int
    {
        return $this->inner->removeAll($entity);
    }
}
