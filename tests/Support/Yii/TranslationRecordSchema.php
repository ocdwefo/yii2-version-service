<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Yii;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RelationSchema;
use Override;

/**
 * @extends RelationSchema<TranslationRecord>
 */
final class TranslationRecordSchema extends RelationSchema
{
    #[Override]
    public function entityClass(): string
    {
        return TranslationRecord::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [Field::string('locale'), Field::string('title')];
    }
}
