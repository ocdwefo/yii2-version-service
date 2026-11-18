<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VersionId::class)]
final class VersionIdTest extends TestCase
{
    private const string UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testGeneratesUuidVersion7(): void
    {
        $id = VersionId::generate(new DateTimeImmutable('2026-11-16 10:00:00.123 UTC'));

        self::assertMatchesRegularExpression(self::UUID_V7, $id->value);
    }

    public function testStartsWithTheTimeInMilliseconds(): void
    {
        $at = new DateTimeImmutable('2026-11-16 10:00:00.123 UTC');
        $milliseconds = (int) $at->format('Uv');

        $hex = str_replace('-', '', VersionId::generate($at)->value);

        self::assertSame(str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT), substr($hex, 0, 12));
    }

    public function testLaterIdSortsAfterEarlierOne(): void
    {
        $earlier = VersionId::generate(new DateTimeImmutable('2026-11-16 10:00:00 UTC'));
        $later = VersionId::generate(new DateTimeImmutable('2026-11-16 10:00:01 UTC'));

        self::assertGreaterThan(0, strcmp($later->value, $earlier->value));
    }

    public function testIdsWithinTheSameMillisecondAreUnique(): void
    {
        $at = new DateTimeImmutable('2026-11-16 10:00:00 UTC');

        $ids = [];
        for ($i = 0; $i < 10_000; $i++) {
            $ids[VersionId::generate($at)->value] = true;
        }

        self::assertCount(10_000, $ids);
    }

    public function testParsesAndNormalizesString(): void
    {
        $id = VersionId::fromString(' 0193A8F2-7C4E-7B21-9F3A-5D2E8C1B4A07 ');

        self::assertSame('0193a8f2-7c4e-7b21-9f3a-5d2e8c1b4a07', $id->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'garbage' => ['not-a-uuid'];
        yield 'version 4' => ['0193a8f2-7c4e-4b21-9f3a-5d2e8c1b4a07'];
        yield 'wrong variant' => ['0193a8f2-7c4e-7b21-1f3a-5d2e8c1b4a07'];
        yield 'without dashes' => ['0193a8f27c4e7b219f3a5d2e8c1b4a07'];
    }

    #[DataProvider('invalidIds')]
    public function testRejectsInvalidString(string $value): void
    {
        $this->expectException(InvalidValueException::class);

        VersionId::fromString($value);
    }
}
