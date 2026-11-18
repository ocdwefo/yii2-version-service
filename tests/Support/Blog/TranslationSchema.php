<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RelationSchema;
use Override;

/**
 * @extends RelationSchema<Translation>
 */
final class TranslationSchema extends RelationSchema
{
    #[Override]
    public function entityClass(): string
    {
        return Translation::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            Field::string('locale'),
            Field::string('title'),
            Field::collection('attachments', AttachmentSchema::class),
        ];
    }
}
