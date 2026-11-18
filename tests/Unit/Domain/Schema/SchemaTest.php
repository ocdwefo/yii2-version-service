<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Domain\Schema\Schema;
use Ocdwefo\VersionService\Tests\Support\Blog\ArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\ConfigurableArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\Translation;
use Ocdwefo\VersionService\Tests\Support\Blog\TranslationSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schema::class)]
#[CoversClass(RootSchema::class)]
final class SchemaTest extends TestCase
{
    public function testFieldsAreReturnedInDeclaredOrder(): void
    {
        $names = array_map(static fn (Field $field): string => $field->name, new ArticleSchema()->getFields());

        self::assertSame(['title', 'status', 'publishedAt', 'price', 'featured', 'tags', 'seo', 'translations'], $names);
    }

    public function testCreatesEmptyInstance(): void
    {
        self::assertInstanceOf(Translation::class, new TranslationSchema()->create());
    }

    public function testNeedsAtLeastOneField(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('at least one field');

        new ConfigurableArticleSchema([])->getFields();
    }

    public function testRejectsDuplicateFieldNames(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('"title" twice');

        new ConfigurableArticleSchema([Field::string('title'), Field::int('title')])->getFields();
    }

    public function testIdFieldDefaultsToId(): void
    {
        self::assertSame('id', new ArticleSchema()->idField());
    }

    public function testKeepVersionsDefaultsToTheConfig(): void
    {
        self::assertNull(new ArticleSchema()->keepVersions());
    }
}
