<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use Override;

/**
 * A schema with an invalid limit of its own.
 *
 * @extends RootSchema<Article>
 */
final class ZeroLimitArticleSchema extends RootSchema
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
        return 0;
    }

    #[Override]
    protected function fields(): array
    {
        return [Field::string('title')];
    }
}
