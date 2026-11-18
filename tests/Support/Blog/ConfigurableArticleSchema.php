<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use Override;

/**
 * Article schema with fields given by the test.
 *
 * @extends RootSchema<Article>
 */
final class ConfigurableArticleSchema extends RootSchema
{
    /**
     * @param list<Field> $definedFields
     */
    public function __construct(
        private readonly array $definedFields,
        private readonly ?int $definedKeepVersions = null,
    ) {
    }

    #[Override]
    public function keepVersions(): ?int
    {
        return $this->definedKeepVersions;
    }

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
        return $this->definedFields;
    }
}
