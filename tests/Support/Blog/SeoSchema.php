<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RelationSchema;
use Override;

/**
 * Fields reached through getters and setters.
 *
 * @extends RelationSchema<Seo>
 */
final class SeoSchema extends RelationSchema
{
    #[Override]
    public function entityClass(): string
    {
        return Seo::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            Field::string('metaTitle'),
            Field::string('metaDescription')->nullable(),
        ];
    }
}
