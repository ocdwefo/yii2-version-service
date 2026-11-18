<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\RetentionLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetentionLimit::class)]
final class RetentionLimitTest extends TestCase
{
    #[TestWith([1])]
    #[TestWith([20])]
    public function testKeepsPositiveLimit(int $value): void
    {
        self::assertSame($value, RetentionLimit::fromInt($value)->value);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function testLatestVersionIsAlwaysKept(int $value): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('latest one is never removed');

        RetentionLimit::fromInt($value);
    }
}
