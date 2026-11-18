<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Ocdwefo\VersionService\Domain\Model\AuthorId;
use Ocdwefo\VersionService\Domain\Model\Comment;
use Ocdwefo\VersionService\Domain\Model\EntityId;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\Snapshot;
use Ocdwefo\VersionService\Domain\Model\Version;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    public function testHoldsEveryPart(): void
    {
        $now = new DateTimeImmutable('2026-11-16 10:00:00 UTC');
        $id = VersionId::generate($now);
        $entity = self::article(42);
        $snapshot = Snapshot::fromArray(['title' => 'Заголовок']);

        $version = new Version(
            $id,
            $entity,
            VersionNumber::fromInt(3),
            $snapshot,
            Comment::fromString('Виправив заголовок'),
            AuthorId::fromInt(5),
            $now,
        );

        self::assertSame($id, $version->id);
        self::assertTrue($version->entity->equals($entity));
        self::assertSame(3, $version->number->value);
        self::assertSame($snapshot, $version->snapshot);
        self::assertSame('Виправив заголовок', $version->comment->value);
        self::assertSame(5, $version->author->value);
        self::assertSame($now, $version->createdAt);
    }

    public function testBelongsToItsEntityOnly(): void
    {
        $now = new DateTimeImmutable('2026-11-16 10:00:00 UTC');
        $version = new Version(
            VersionId::generate($now),
            self::article(42),
            VersionNumber::first(),
            Snapshot::fromArray(['title' => 'A']),
            Comment::fromString('Перша версія'),
            AuthorId::fromInt(5),
            $now,
        );

        self::assertTrue($version->belongsTo(self::article(42)));
        self::assertFalse($version->belongsTo(self::article(43)));
        self::assertFalse($version->belongsTo(new EntityReference(EntityType::fromInt(2), EntityId::fromInt(42))));
    }

    private static function article(int $id): EntityReference
    {
        return new EntityReference(EntityType::fromEnum(TestEntityType::Article), EntityId::fromInt($id));
    }
}
