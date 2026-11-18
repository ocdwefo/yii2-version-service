<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Application;

use DateTimeImmutable;
use Ocdwefo\VersionService\Domain\Model\Version;

/**
 * A version as the project sees it: metadata plus the stored values of the fields.
 *
 * The values are plain, as stored: strings, numbers, booleans, null, arrays; a relation is an array of its fields,
 * a collection a list of such arrays. For the entity itself with these values use EntityVersions::checkout().
 */
final readonly class VersionView
{
    /**
     * @param array<string, mixed> $fields Stored values by field name.
     */
    public function __construct(
        public string $id,
        public int $entityId,
        public int $number,
        public int $authorId,
        public DateTimeImmutable $createdAt,
        public string $comment,
        public array $fields,
    ) {
    }

    public static function fromVersion(Version $version): self
    {
        return new self(
            $version->id->value,
            $version->entity->id->value,
            $version->number->value,
            $version->author->value,
            $version->createdAt,
            $version->comment->value,
            $version->snapshot->data,
        );
    }
}
