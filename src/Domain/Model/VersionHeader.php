<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use DateTimeImmutable;

/**
 * A version without its snapshot: what a history list needs, cheap to load.
 */
final readonly class VersionHeader
{
    public function __construct(
        public VersionId $id,
        public EntityReference $entity,
        public VersionNumber $number,
        public Comment $comment,
        public AuthorId $author,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
