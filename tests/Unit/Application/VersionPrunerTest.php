<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Application;

use Ocdwefo\VersionService\Application\EntityVersions;
use Ocdwefo\VersionService\Application\VersionPruner;
use Ocdwefo\VersionService\Application\VersionService;
use Ocdwefo\VersionService\Application\VersionSummary;
use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Schema\ObjectAccessor;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Infrastructure\Persistence\InMemoryVersionRepository;
use Ocdwefo\VersionService\Tests\Support\Blog\Article;
use Ocdwefo\VersionService\Tests\Support\Blog\ArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\KeepTwoArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\SecondArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\TranslationSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\ZeroLimitArticleSchema;
use Ocdwefo\VersionService\Tests\Support\FixedClock;
use Ocdwefo\VersionService\Tests\Support\StaticAuthorResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VersionPruner::class)]
#[CoversClass(VersionService::class)]
final class VersionPrunerTest extends TestCase
{
    private InMemoryVersionRepository $repository;

    private VersionService $service;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryVersionRepository();
        $this->service = new VersionService(
            $this->repository,
            new StaticAuthorResolver(),
            FixedClock::at('2026-11-16 10:00:00 UTC'),
            new ObjectAccessor(),
        );
    }

    public function testKeepsTheNewestVersionsOfEveryEntity(): void
    {
        $articles = $this->service->of(new ArticleSchema());
        $this->recordArticle($articles, 42, 5);
        $this->recordArticle($articles, 43, 1);

        self::assertSame(2, $this->pruner(3, [ArticleSchema::class])->prune());
        self::assertSame([5, 4, 3], array_map(static fn (VersionSummary $version): int => $version->number, $articles->history(42)->items));
        self::assertSame(1, $articles->history(43)->total);
    }

    public function testLatestVersionIsAlwaysKept(): void
    {
        $articles = $this->service->of(new ArticleSchema());
        $this->recordArticle($articles, 42, 3);

        self::assertSame(2, $this->pruner(1, [ArticleSchema::class])->prune());
        self::assertSame(3, $articles->history(42)->items[0]->number);
    }

    public function testSchemaMissingFromTheListIsNotPruned(): void
    {
        $articles = $this->service->of(new ArticleSchema());
        $this->recordArticle($articles, 42, 3);

        self::assertSame(0, $this->pruner(1, [])->prune());
        self::assertSame(3, $articles->history(42)->total);
    }

    public function testSchemaLimitOverridesTheDefault(): void
    {
        $articles = $this->service->of(new ArticleSchema());
        $this->recordArticle($articles, 42, 5);

        self::assertSame(3, $this->pruner(10, [KeepTwoArticleSchema::class])->prune());
        self::assertSame(2, $articles->history(42)->total);
    }

    public function testRejectsZeroLimit(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->pruner(0, []);
    }

    public function testRejectsInvalidSchemaLimit(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('keepVersions() must be at least 1');

        $this->pruner(10, [ZeroLimitArticleSchema::class])->prune();
    }

    public function testRejectsSchemasSharingEntityType(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('use the same entity type 1');

        $this->pruner(10, [ArticleSchema::class, SecondArticleSchema::class])->prune();
    }

    public function testRejectsSchemaThatIsNotVersionSchema(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage(TranslationSchema::class);

        /** @phpstan-ignore argument.type (the point of the test) */
        $this->pruner(10, [TranslationSchema::class])->prune();
    }

    /**
     * @param list<class-string<RootSchema<covariant object>>> $schemas
     */
    private function pruner(int $keepVersions, array $schemas): VersionPruner
    {
        return new VersionPruner($keepVersions, $schemas, $this->repository);
    }

    /**
     * @param EntityVersions<Article> $articles
     */
    private function recordArticle(EntityVersions $articles, int $id, int $versions): void
    {
        $article = new Article();
        $article->id = $id;
        for ($i = 1; $i <= $versions; $i++) {
            $articles->record($article, 'Версія ' . $i);
        }
    }
}
