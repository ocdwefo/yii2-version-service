<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Yii;

use Override;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $article_id
 * @property string $locale
 * @property string $title
 */
final class TranslationRecord extends ActiveRecord
{
    /** Set by the test, which creates the table with a random name. */
    public static string $table = 'vs_test_translation';

    #[Override]
    public static function tableName(): string
    {
        return self::$table;
    }
}
