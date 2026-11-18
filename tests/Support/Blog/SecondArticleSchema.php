<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use Override;

/**
 * Another schema of the same entity type as ArticleSchema.
 *
 * @extends RootSchema<Article>
 */
final class SecondArticleSchema extends RootSchema
{
    #[Override]
    public function entityType(): TestEntityType
    {
        return TestEntityType::Article;
    }

    #[Override]
    public function entityClass(): string
    {
        return Article::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [Field::string('title')];
    }
}
