<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use DateTimeImmutable;

/**
 * Aggregate root: a stored state of an entity.
 *
 * A version is immutable. It is created once, read, and eventually removed by pruning;
 * it is never changed. Every part is required. New versions come from
 * {@see \Ocdwefo\VersionService\Domain\Service\VersionFactory}, which assigns identity, number and time.
 */
final readonly class Version
{
    public function __construct(
        public VersionId $id,
        public EntityReference $entity,
        public VersionNumber $number,
        public Snapshot $snapshot,
        public Comment $comment,
        public AuthorId $author,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public function belongsTo(EntityReference $entity): bool
    {
        return $this->entity->equals($entity);
    }
}
