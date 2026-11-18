<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use Override;

/**
 * @extends RootSchema<LegacyDocument>
 */
final class LegacyDocumentSchema extends RootSchema
{
    #[Override]
    public function entityType(): TestEntityType
    {
        return TestEntityType::Page;
    }

    #[Override]
    public function entityClass(): string
    {
        return LegacyDocument::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [Field::string('title')];
    }
}
