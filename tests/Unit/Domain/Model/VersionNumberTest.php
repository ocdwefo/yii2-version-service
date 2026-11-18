<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(VersionNumber::class)]
final class VersionNumberTest extends TestCase
{
    public function testSequence(): void
    {
        $first = VersionNumber::first();

        self::assertSame(1, $first->value);
        self::assertSame(2, $first->next()->value);
        self::assertSame(1, $first->value, 'next() does not change the number itself');
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function testRejectsNotPositive(int $value): void
    {
        $this->expectException(InvalidValueException::class);

        VersionNumber::fromInt($value);
    }
}
