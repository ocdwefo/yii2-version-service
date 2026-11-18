<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use Override;

/**
 * A schema keeping two versions, whatever the default.
 *
 * @extends RootSchema<Article>
 */
final class KeepTwoArticleSchema extends RootSchema
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
    public function keepVersions(): int
    {
        return 2;
    }

    #[Override]
    protected function fields(): array
    {
        return [Field::string('title')];
    }
}
