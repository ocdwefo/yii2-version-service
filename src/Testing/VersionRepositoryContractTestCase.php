<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Testing;

use DateTimeImmutable;
use DateTimeZone;
use Ocdwefo\VersionService\Domain\Exception\VersionConflictException;
use Ocdwefo\VersionService\Domain\Model\AuthorId;
use Ocdwefo\VersionService\Domain\Model\Comment;
use Ocdwefo\VersionService\Domain\Model\EntityId;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\RetentionLimit;
use Ocdwefo\VersionService\Domain\Model\Snapshot;
use Ocdwefo\VersionService\Domain\Model\Version;
use Ocdwefo\VersionService\Domain\Model\VersionHeader;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour every {@see VersionRepositoryInterface} must have.
 *
 * A project writing its own repository extends this class and returns a clean repository:
 *
 * ```php
 * final class RedisVersionRepositoryTest extends VersionRepositoryContractTestCase
 * {
 *     protected function createRepository(): VersionRepositoryInterface
 *     {
 *         $this->redis->flushDb();
 *
 *         return new RedisVersionRepository($this->redis);
 *     }
 * }
 * ```
 */
abstract class VersionRepositoryContractTestCase extends TestCase
{
    protected VersionRepositoryInterface $repository;

    /**
     * A repository without any versions; called before every test.
     */
    abstract protected function createRepository(): VersionRepositoryInterface;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createRepository();
    }

    public function testStoresEveryPartOfVersion(): void
    {
        $createdAt = new DateTimeImmutable('2026-11-16 12:00:00.123456', new DateTimeZone('Europe/Kyiv'));
        $snapshot = Snapshot::fromArray([
            'title' => 'Стаття "з лапками" і \\ слешем',
            'price' => 9.5,
            'weight' => 2.0, // stays a float, not 2
            'published' => true,
            'deleted_at' => null,
            'tags' => ['php', 'yii'],
            'translations' => [['locale' => 'en', 'title' => 'Article', 'attachments' => [['name' => 'a.pdf', 'size' => 1024]]]],
        ]);
        $version = new Version(
            self::versionId($createdAt),
            self::entity(42),
            VersionNumber::fromInt(3),
            $snapshot,
            Comment::fromString('Виправив заголовок ✓'),
            AuthorId::fromInt(PHP_INT_MAX),
            $createdAt,
        );

        $this->repository->add($version);
        $found = $this->repository->find($version->id);

        self::assertNotNull($found);
        self::assertSame($version->id->value, $found->id->value);
        self::assertTrue($found->entity->equals(self::entity(42)));
        self::assertSame(3, $found->number->value);
        self::assertSame(
            self::sortKeys($snapshot->data),
            self::sortKeys($found->snapshot->data),
            'snapshot data survives storage with types, the order of keys may change (MySQL JSON reorders them)',
        );
        self::assertSame('Виправив заголовок ✓', $found->comment->value);
        self::assertSame(PHP_INT_MAX, $found->author->value);
        self::assertSame(
            $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            $found->createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'the moment survives storage with microseconds',
        );
    }

    public function testHistoryHoldsEverythingButTheSnapshot(): void
    {
        $createdAt = new DateTimeImmutable('2026-11-16 12:00:00.123456', new DateTimeZone('UTC'));
        $version = new Version(
            self::versionId($createdAt),
            self::entity(42),
            VersionNumber::fromInt(1),
            Snapshot::fromArray(['title' => 'Стаття']),
            Comment::fromString('Створив'),
            AuthorId::fromInt(5),
            $createdAt,
        );
        $this->repository->add($version);

        $header = $this->repository->history(self::entity(42), 10)[0] ?? null;

        self::assertNotNull($header);
        self::assertSame($version->id->value, $header->id->value);
        self::assertTrue($header->entity->equals(self::entity(42)));
        self::assertSame(1, $header->number->value);
        self::assertSame('Створив', $header->comment->value);
        self::assertSame(5, $header->author->value);
        self::assertSame('2026-11-16 12:00:00.123456', $header->createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'));
    }

    public function testUnknownVersionIsNotFound(): void
    {
        $this->add(self::entity(42), 1);

        self::assertNull($this->repository->find(VersionId::fromString('0193a8f2-7c4e-7b21-9f3a-5d2e8c1b4a07')));
    }

    public function testRejectsTakenNumber(): void
    {
        $this->add(self::entity(42), 1);

        $this->expectException(VersionConflictException::class);

        $this->add(self::entity(42), 1);
    }

    public function testSameNumberOfAnotherEntityIsFine(): void
    {
        $this->add(self::entity(42), 1);
        $this->add(self::entity(43), 1);
        $this->add(self::entity(42, type: 2), 1);

        self::assertSame(1, $this->repository->count(self::entity(42)));
        self::assertSame(1, $this->repository->count(self::entity(43)));
        self::assertSame(1, $this->repository->count(self::entity(42, type: 2)));
    }

    public function testNextNumberFollowsTheHighest(): void
    {
        self::assertSame(1, $this->repository->nextNumber(self::entity(42))->value);

        $this->add(self::entity(42), 1);
        $this->add(self::entity(42), 2);
        $this->add(self::entity(43), 7);

        self::assertSame(3, $this->repository->nextNumber(self::entity(42))->value);
        self::assertSame(8, $this->repository->nextNumber(self::entity(43))->value);
        self::assertSame(1, $this->repository->nextNumber(self::entity(42, type: 2))->value);
    }

    public function testHistoryIsNewestFirstAndPaged(): void
    {
        foreach ([1, 2, 3, 4, 5] as $number) {
            $this->add(self::entity(42), $number);
        }
        $this->add(self::entity(43), 1);

        self::assertSame([5, 4, 3, 2, 1], self::numbers($this->repository->history(self::entity(42), 10)));
        self::assertSame([4, 3], self::numbers($this->repository->history(self::entity(42), 2, 1)));
        self::assertSame([], $this->repository->history(self::entity(42), 2, 10));
        self::assertSame([], $this->repository->history(self::entity(44), 10));
        self::assertSame(5, $this->repository->count(self::entity(42)));
        self::assertSame(0, $this->repository->count(self::entity(44)));
    }

    public function testPruneKeepsTheNewestVersionsOfEveryEntityOfTheType(): void
    {
        foreach ([1, 2, 3, 4, 5] as $number) {
            $this->add(self::entity(42), $number);
        }
        foreach ([1, 2, 3] as $number) {
            $this->add(self::entity(43), $number);
            $this->add(self::entity(42, type: 2), $number);
        }
        $this->add(self::entity(44), 1);

        self::assertSame(4, $this->repository->prune(EntityType::fromInt(1), RetentionLimit::fromInt(2)));
        self::assertSame([5, 4], self::numbers($this->repository->history(self::entity(42), 10)));
        self::assertSame([3, 2], self::numbers($this->repository->history(self::entity(43), 10)));
        self::assertSame(1, $this->repository->count(self::entity(44)), 'an entity within the limit is untouched');
        self::assertSame(3, $this->repository->count(self::entity(42, type: 2)), 'entities of other types are untouched');
        self::assertSame(0, $this->repository->prune(EntityType::fromInt(1), RetentionLimit::fromInt(2)), 'pruning twice removes nothing more');
    }

    public function testPruneWithinTheLimitRemovesNothing(): void
    {
        $this->add(self::entity(42), 1);

        self::assertSame(0, $this->repository->prune(EntityType::fromInt(1), RetentionLimit::fromInt(1)));
        self::assertSame(0, $this->repository->prune(EntityType::fromInt(3), RetentionLimit::fromInt(1)), 'a type without versions');
        self::assertSame(1, $this->repository->count(self::entity(42)));
    }

    public function testRemoveAllClearsOneEntity(): void
    {
        $this->add(self::entity(42), 1);
        $this->add(self::entity(42), 2);
        $this->add(self::entity(43), 1);

        self::assertSame(2, $this->repository->removeAll(self::entity(42)));
        self::assertSame(0, $this->repository->count(self::entity(42)));
        self::assertSame(1, $this->repository->count(self::entity(43)));
        self::assertSame(0, $this->repository->removeAll(self::entity(42)));
    }

    protected function add(EntityReference $entity, int $number): Version
    {
        $createdAt = new DateTimeImmutable('2026-11-16 10:00:00', new DateTimeZone('UTC'));
        $version = new Version(
            self::versionId($createdAt),
            $entity,
            VersionNumber::fromInt($number),
            Snapshot::fromArray(['title' => 'Версія ' . $number]),
            Comment::fromString('Версія ' . $number),
            AuthorId::fromInt(5),
            $createdAt,
        );

        $this->repository->add($version);

        return $version;
    }

    protected static function entity(int $id, int $type = 1): EntityReference
    {
        return new EntityReference(EntityType::fromInt($type), EntityId::fromInt($id));
    }

    /**
     * @param list<VersionHeader> $versions
     *
     * @return list<int>
     */
    private static function numbers(array $versions): array
    {
        return array_map(static fn (VersionHeader $version): int => $version->number->value, $versions);
    }

    /**
     * Associative keys sorted recursively, lists keep their order.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function sortKeys(array $data): array
    {
        if (!array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortKeys($value);
            }
        }

        return $data;
    }

    private static function versionId(DateTimeImmutable $at): VersionId
    {
        return VersionId::generate($at);
    }
}
