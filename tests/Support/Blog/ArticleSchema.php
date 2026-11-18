<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use DateTimeImmutable;
use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use Override;

/**
 * @extends RootSchema<Article>
 */
final class ArticleSchema extends RootSchema
{
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

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
        return [
            Field::string('title'),
            // An enum is kept as its value.
            Field::string('status')
                ->readUsing(static fn (Article $article): string => $article->status->value)
                ->writeUsing(static function (Article $article, mixed $value): void {
                    $article->status = ArticleStatus::from(is_string($value) ? $value : '');
                }),
            // A date object is kept as a string in the format the project chooses.
            Field::string('publishedAt')->nullable()
                ->readUsing(static fn (Article $article): ?string => $article->publishedAt?->format(self::DATE_FORMAT))
                ->writeUsing(static function (Article $article, mixed $value): void {
                    $article->publishedAt = is_string($value) ? new DateTimeImmutable($value) : null;
                }),
            Field::float('price'),
            Field::bool('featured'),
            Field::array('tags'),
            Field::object('seo', SeoSchema::class)->nullable(),
            Field::collection('translations', TranslationSchema::class),
        ];
    }
}
