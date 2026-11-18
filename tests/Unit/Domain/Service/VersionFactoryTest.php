<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Service;

use DateTimeImmutable;
use Ocdwefo\VersionService\Domain\Model\AuthorId;
use Ocdwefo\VersionService\Domain\Model\Comment;
use Ocdwefo\VersionService\Domain\Model\EntityId;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\Snapshot;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Domain\Service\VersionFactory;
use Ocdwefo\VersionService\Tests\Support\FixedClock;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(VersionFactory::class)]
final class VersionFactoryTest extends TestCase
{
    public function testAssignsNextNumberCurrentTimeAndUuidV7(): void
    {
        $entity = new EntityReference(EntityType::fromInt(1), EntityId::fromInt(42));
        $clock = FixedClock::at('2026-11-16 10:00:00.250 UTC');

        $repository = $this->createMock(VersionRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('nextNumber')
            ->with($entity)
            ->willReturn(VersionNumber::fromInt(7));

        $snapshot = Snapshot::fromArray(['title' => 'Стаття']);

        $version = new VersionFactory($repository, $clock)->create(
            $entity,
            $snapshot,
            Comment::fromString('Виправив заголовок'),
            AuthorId::fromInt(5),
        );

        self::assertSame(7, $version->number->value);
        self::assertSame($clock->now(), $version->createdAt);
        self::assertTrue($version->entity->equals($entity));
        self::assertSame($snapshot, $version->snapshot);
        self::assertSame('Виправив заголовок', $version->comment->value);
        self::assertSame(5, $version->author->value);

        $milliseconds = (int) $clock->now()->format('Uv');
        self::assertStringStartsWith(
            substr(str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT), 0, 8),
            $version->id->value,
            'the id carries the creation time',
        );
    }

    /**
     * A clock moving on every call: the id and the creation time still come from one reading.
     */
    public function testIdAndCreationTimeAreTheSameMoment(): void
    {
        $clock = new class implements ClockInterface {
            private int $calls = 0;

            #[Override]
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(sprintf('2026-11-16 10:00:00.%03d UTC', ++$this->calls * 7));
            }
        };

        $repository = self::createStub(VersionRepositoryInterface::class);
        $repository->method('nextNumber')->willReturn(VersionNumber::first());

        $version = new VersionFactory($repository, $clock)->create(
            new EntityReference(EntityType::fromInt(1), EntityId::fromInt(42)),
            Snapshot::fromArray(['title' => 'Стаття']),
            Comment::fromString('Створив'),
            AuthorId::fromInt(5),
        );

        $milliseconds = (int) $version->createdAt->format('Uv');
        self::assertSame(
            str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT),
            substr(str_replace('-', '', $version->id->value), 0, 12),
        );
    }
}
