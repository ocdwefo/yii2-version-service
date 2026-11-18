<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RelationSchema;
use Override;

/**
 * @extends RelationSchema<Attachment>
 */
final class AttachmentSchema extends RelationSchema
{
    #[Override]
    public function entityClass(): string
    {
        return Attachment::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [Field::string('name'), Field::int('size')];
    }
}
