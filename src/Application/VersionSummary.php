<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Application;

use DateTimeImmutable;
use Ocdwefo\VersionService\Domain\Model\VersionHeader;

/**
 * An item of the history list: who, when and why, without the stored values.
 * The values of a version come from {@see EntityVersions::get()} or {@see EntityVersions::checkout()}.
 */
final readonly class VersionSummary
{
    public function __construct(
        public string $id,
        public int $entityId,
        public int $number,
        public int $authorId,
        public DateTimeImmutable $createdAt,
        public string $comment,
    ) {
    }

    public static function fromHeader(VersionHeader $header): self
    {
        return new self(
            $header->id->value,
            $header->entity->id->value,
            $header->number->value,
            $header->author->value,
            $header->createdAt,
            $header->comment->value,
        );
    }
}
