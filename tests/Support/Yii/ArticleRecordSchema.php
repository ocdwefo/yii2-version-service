<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Yii;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use Override;

/**
 * @extends RootSchema<ArticleRecord>
 */
final class ArticleRecordSchema extends RootSchema
{
    #[Override]
    public function entityType(): TestEntityType
    {
        return TestEntityType::Article;
    }

    #[Override]
    public function entityClass(): string
    {
        return ArticleRecord::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            Field::string('title'),
            Field::int('status'),
            Field::string('published_at')->nullable(),
            Field::collection('translations', TranslationRecordSchema::class),
        ];
    }
}
