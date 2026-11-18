<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\UnknownAuthorException;
use Ocdwefo\VersionService\Domain\Exception\VersionConflictException;
use Ocdwefo\VersionService\Domain\Exception\VersionNotFoundException;
use Ocdwefo\VersionService\Domain\Model\EntityId;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(VersionConflictException::class)]
#[CoversClass(VersionNotFoundException::class)]
#[CoversClass(UnknownAuthorException::class)]
final class ExceptionMessagesTest extends TestCase
{
    public function testConflictNamesEntityAndNumber(): void
    {
        $previous = new RuntimeException('duplicate key');
        $exception = VersionConflictException::numberTaken(self::article(), VersionNumber::fromInt(6), $previous);

        self::assertSame('Version 6 of entity 1#42 is already taken by a concurrent write.', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testNotFoundNamesVersionAndEntity(): void
    {
        $exception = VersionNotFoundException::of(VersionId::fromString('0193a8f2-7c4e-7b21-9f3a-5d2e8c1b4a07'), self::article());

        self::assertSame('Version 0193a8f2-7c4e-7b21-9f3a-5d2e8c1b4a07 of entity 1#42 not found.', $exception->getMessage());
    }

    public function testUnknownAuthorTellsWhatToDo(): void
    {
        self::assertSame(
            'Nobody is logged in (console application): pass the author to record() as authorId.',
            UnknownAuthorException::nobodyLoggedIn('console application')->getMessage(),
        );
    }

    private static function article(): EntityReference
    {
        return new EntityReference(EntityType::fromInt(1), EntityId::fromInt(42));
    }
}
