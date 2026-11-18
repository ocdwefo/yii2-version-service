<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\Snapshot;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Snapshot::class)]
final class SnapshotTest extends TestCase
{
    public function testKeepsDataAsGiven(): void
    {
        $data = ['title' => 'Заголовок', 'status' => 1, 'price' => 9.5, 'published' => false, 'deleted_at' => null, 'tags' => ['a', 'b']];

        self::assertSame($data, Snapshot::fromArray($data)->data);
    }

    public function testAcceptsNestedRelations(): void
    {
        $data = [
            'title' => 'Стаття',
            'translations' => [
                ['locale' => 'en', 'title' => 'Article', 'attachments' => [['name' => 'a.pdf', 'size' => 1024]]],
                ['locale' => 'uk', 'title' => 'Стаття', 'attachments' => []],
            ],
        ];

        self::assertSame($data, Snapshot::fromArray($data)->data);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidData(): iterable
    {
        yield 'empty' => [[], 'at least one field'];
        yield 'list instead of fields' => [['a', 'b'], 'keyed by field names'];
        yield 'object' => [['payload' => new stdClass()], '"payload"'];
        yield 'date object' => [['published_at' => new DateTimeImmutable()], '"published_at"'];
        yield 'enum' => [['type' => TestEntityType::Article], '"type"'];
        yield 'nested object' => [['translations' => [['meta' => new stdClass()]]], '"translations.0.meta"'];
        yield 'NAN' => [['value' => NAN], 'finite'];
        yield 'INF' => [['value' => INF], 'finite'];
        yield 'invalid UTF-8' => [['title' => "\xC3\x28"], '"title" must be valid UTF-8'];
        yield 'invalid UTF-8 deep' => [['seo' => ['tags' => ['ok', "\xC3\x28"]]], '"seo.tags.1" must be valid UTF-8'];
        yield 'invalid UTF-8 key' => [['seo' => ["\xC3\x28" => 'x']], '"seo" must be valid UTF-8'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('invalidData')]
    public function testRejectsInvalidData(array $data, string $message): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage($message);

        Snapshot::fromArray($data);
    }
}
