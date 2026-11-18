<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\AuthorId;
use Ocdwefo\VersionService\Domain\Model\EntityId;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Tests\Support\StringBackedType;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityType::class)]
#[CoversClass(EntityId::class)]
#[CoversClass(AuthorId::class)]
#[CoversClass(EntityReference::class)]
final class IdentifiersTest extends TestCase
{
    public function testEntityTypeFromIntBackedEnum(): void
    {
        $type = EntityType::fromEnum(TestEntityType::Page);

        self::assertSame(2, $type->value);
    }

    public function testEntityTypeRejectsStringBackedEnum(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('StringBackedType::Article');

        EntityType::fromEnum(StringBackedType::Article);
    }

    public function testEntityTypeRejectsValueOutOfSmallintRange(): void
    {
        $this->expectException(InvalidValueException::class);

        EntityType::fromEnum(TestEntityType::OutOfRange);
    }

    #[TestWith([0])]
    #[TestWith([-5])]
    #[TestWith([EntityType::MAX + 1])]
    public function testEntityTypeRejectsInvalidInt(int $value): void
    {
        $this->expectException(InvalidValueException::class);

        EntityType::fromInt($value);
    }

    public function testEntityTypeAcceptsBounds(): void
    {
        self::assertSame(1, EntityType::fromInt(1)->value);
        self::assertSame(EntityType::MAX, EntityType::fromInt(EntityType::MAX)->value);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function testEntityIdMustBePositive(int $value): void
    {
        $this->expectException(InvalidValueException::class);

        EntityId::fromInt($value);
    }

    public function testEntityIdAcceptsBigint(): void
    {
        self::assertSame(PHP_INT_MAX, EntityId::fromInt(PHP_INT_MAX)->value);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function testAuthorIdMustBePositive(int $value): void
    {
        $this->expectException(InvalidValueException::class);

        AuthorId::fromInt($value);
    }

    public function testEntityReferenceEquality(): void
    {
        $article42 = new EntityReference(EntityType::fromEnum(TestEntityType::Article), EntityId::fromInt(42));

        self::assertTrue($article42->equals(new EntityReference(EntityType::fromInt(1), EntityId::fromInt(42))));
        self::assertFalse($article42->equals(new EntityReference(EntityType::fromInt(2), EntityId::fromInt(42))));
        self::assertFalse($article42->equals(new EntityReference(EntityType::fromInt(1), EntityId::fromInt(43))));
    }
}
