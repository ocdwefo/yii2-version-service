<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Yii;

use Override;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $title
 * @property int $status
 * @property string|null $published_at
 * @property-read list<TranslationRecord> $translations
 */
final class ArticleRecord extends ActiveRecord
{
    /** Set by the test, which creates the table with a random name. */
    public static string $table = 'vs_test_article';

    #[Override]
    public static function tableName(): string
    {
        return self::$table;
    }

    public function getTranslations(): ActiveQueryInterface
    {
        return $this->hasMany(TranslationRecord::class, ['article_id' => 'id'])->orderBy(['locale' => SORT_ASC]);
    }
}
