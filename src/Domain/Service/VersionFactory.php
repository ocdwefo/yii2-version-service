<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Service;

use Ocdwefo\VersionService\Domain\Model\AuthorId;
use Ocdwefo\VersionService\Domain\Model\Comment;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\Snapshot;
use Ocdwefo\VersionService\Domain\Model\Version;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Psr\Clock\ClockInterface;

/**
 * Factory giving a new version its identity, number and creation time.
 *
 * The number is the next free one at the moment of the call. When a concurrent process takes it first,
 * storing the version fails with a conflict and the caller creates it again.
 */
final readonly class VersionFactory
{
    public function __construct(
        private VersionRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function create(EntityReference $entity, Snapshot $snapshot, Comment $comment, AuthorId $author): Version
    {
        $now = $this->clock->now(); // read once: the id and the creation time are the same moment

        return new Version(
            VersionId::generate($now),
            $entity,
            $this->repository->nextNumber($entity),
            $snapshot,
            $comment,
            $author,
            $now,
        );
    }
}
