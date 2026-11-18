<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\FieldType;
use Ocdwefo\VersionService\Domain\Schema\RelationSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\Article;
use Ocdwefo\VersionService\Tests\Support\Blog\ArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\TranslationSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
final class FieldTest extends TestCase
{
    public function testFactoriesSetTheType(): void
    {
        self::assertSame(FieldType::String, Field::string('a')->type);
        self::assertSame(FieldType::Int, Field::int('a')->type);
        self::assertSame(FieldType::Float, Field::float('a')->type);
        self::assertSame(FieldType::Bool, Field::bool('a')->type);
        self::assertSame(FieldType::Array, Field::array('a')->type);

        $collection = Field::collection('translations', TranslationSchema::class);
        self::assertSame(FieldType::Collection, $collection->type);
        self::assertSame(TranslationSchema::class, $collection->schemaClass);
        self::assertSame(FieldType::Object, Field::object('seo', TranslationSchema::class)->type);
    }

    public function testNotNullableByDefault(): void
    {
        self::assertFalse(Field::string('title')->nullable);
    }

    public function testNullableReturnsNewField(): void
    {
        $field = Field::string('title');
        $nullable = $field->nullable();

        self::assertTrue($nullable->nullable);
        self::assertFalse($field->nullable, 'the original field is immutable');
        self::assertSame('title', $nullable->name);
    }

    public function testObjectCanBeNullable(): void
    {
        self::assertTrue(Field::object('seo', TranslationSchema::class)->nullable()->nullable);
    }

    public function testCollectionCannotBeNullable(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('empty list');

        Field::collection('translations', TranslationSchema::class)->nullable();
    }

    public function testCustomReaderAndWriterKeepOtherSettings(): void
    {
        $reader = static fn (object $owner): string => 'read';
        $writer = static function (object $owner, mixed $value): void {
        };

        $field = Field::string('slug')->nullable()->readUsing($reader)->writeUsing($writer);

        self::assertTrue($field->nullable);
        self::assertSame($reader, $field->reader);
        self::assertSame($writer, $field->writer);
    }

    #[TestWith([''])]
    #[TestWith(['1title'])]
    #[TestWith(['meta-title'])]
    #[TestWith(['meta title'])]
    public function testRejectsInvalidName(string $name): void
    {
        $this->expectException(InvalidSchemaException::class);

        Field::string($name);
    }

    public function testRelationNeedsSchemaClass(): void
    {
        $this->expectException(InvalidSchemaException::class);

        /** @phpstan-ignore argument.type (the point of the test) */
        Field::collection('translations', Article::class);
    }

    public function testRelationCannotPointToRootSchema(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('does not extend ' . RelationSchema::class);

        /** @phpstan-ignore argument.type (the point of the test) */
        Field::object('article', ArticleSchema::class);
    }
}
