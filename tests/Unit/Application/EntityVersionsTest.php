<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Ocdwefo\VersionService\Application\EntityVersions;
use Ocdwefo\VersionService\Application\VersionPage;
use Ocdwefo\VersionService\Application\VersionService;
use Ocdwefo\VersionService\Application\VersionSummary;
use Ocdwefo\VersionService\Application\VersionView;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Exception\VersionConflictException;
use Ocdwefo\VersionService\Domain\Exception\VersionNotFoundException;
use Ocdwefo\VersionService\Domain\Model\EntityId;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Domain\Schema\ObjectAccessor;
use Ocdwefo\VersionService\Infrastructure\Persistence\InMemoryVersionRepository;
use Ocdwefo\VersionService\Tests\Support\Blog\Article;
use Ocdwefo\VersionService\Tests\Support\Blog\ArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\ArticleStatus;
use Ocdwefo\VersionService\Tests\Support\Blog\Seo;
use Ocdwefo\VersionService\Tests\Support\Blog\Translation;
use Ocdwefo\VersionService\Tests\Support\ConflictingRepository;
use Ocdwefo\VersionService\Tests\Support\FixedClock;
use Ocdwefo\VersionService\Tests\Support\StaticAuthorResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityVersions::class)]
#[CoversClass(VersionView::class)]
#[CoversClass(VersionPage::class)]
#[CoversClass(VersionSummary::class)]
final class EntityVersionsTest extends TestCase
{
    private InMemoryVersionRepository $repository;

    private StaticAuthorResolver $authors;

    private FixedClock $clock;

    /** @var EntityVersions<Article> */
    private EntityVersions $versions;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryVersionRepository();
        $this->authors = new StaticAuthorResolver();
        $this->clock = FixedClock::at('2026-11-16 10:00:00 UTC');
        $this->versions = $this->service($this->repository)->of(new ArticleSchema());
    }

    // ---- record -----------------------------------------------------------------------------------

    public function testRecordStoresTheCurrentState(): void
    {
        $this->authors->current = 5;

        $view = $this->versions->record(self::article(), '  Створив статтю ');

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7/', $view->id);
        self::assertSame(42, $view->entityId);
        self::assertSame(1, $view->number);
        self::assertSame(5, $view->authorId);
        self::assertSame('Створив статтю', $view->comment);
        self::assertEquals($this->clock->now(), $view->createdAt);
        self::assertSame('Стаття', $view->fields['title']);
        self::assertSame('published', $view->fields['status']);
        self::assertSame(1, $this->repository->count($this->reference(42)));
    }

    public function testRecordAlwaysCreatesNewVersion(): void
    {
        $article = self::article();

        $this->versions->record($article, 'Перша');
        $second = $this->versions->record($article, 'Нічого не змінив, але зберіг');

        self::assertSame(2, $second->number);
    }

    public function testNumbersArePerEntity(): void
    {
        $this->versions->record(self::article(42), 'A');
        $this->versions->record(self::article(42), 'B');

        self::assertSame(1, $this->versions->record(self::article(43), 'C')->number);
    }

    public function testExplicitAuthorWins(): void
    {
        $this->authors->current = 5;

        self::assertSame(7, $this->versions->record(self::article(), 'Імпорт', authorId: 7)->authorId);
    }

    public function testAuthorComesFromTheResolver(): void
    {
        $this->authors->current = 9;

        self::assertSame(9, $this->versions->record(self::article(), 'Крон')->authorId);
    }

    public function testInvalidCommentStoresNothing(): void
    {
        try {
            $this->versions->record(self::article(), '   ');
            self::fail('An empty comment must be rejected.');
        } catch (InvalidValueException) {
            self::assertSame(0, $this->repository->count($this->reference(42)));
        }
    }

    public function testRecordRetriesWhenNumberIsTaken(): void
    {
        $repository = new ConflictingRepository(conflicts: 2);
        $versions = $this->service($repository)->of(new ArticleSchema());

        $view = $versions->record(self::article(), 'Зберіг');

        self::assertSame(3, $repository->attempts);
        self::assertSame(1, $view->number);
        self::assertSame(1, $repository->count($this->reference(42)));
    }

    public function testRecordGivesUpAfterThreeConflicts(): void
    {
        $repository = new ConflictingRepository(conflicts: 3);
        $versions = $this->service($repository)->of(new ArticleSchema());

        try {
            $versions->record(self::article(), 'Зберіг');
            self::fail('The conflict must surface after the last attempt.');
        } catch (VersionConflictException) {
            self::assertSame(3, $repository->attempts);
        }
    }

    // ---- history / get ----------------------------------------------------------------------------

    public function testHistoryIsNewestFirstWithTotal(): void
    {
        foreach (['Перша', 'Друга', 'Третя'] as $comment) {
            $this->versions->record(self::article(), $comment);
            $this->clock->advance('PT1M');
        }

        $page = $this->versions->history(42, limit: 2);

        self::assertSame(3, $page->total);
        self::assertSame(['Третя', 'Друга'], array_map(static fn (VersionSummary $version): string => $version->comment, $page->items));
        self::assertSame(['Перша'], array_map(static fn (VersionSummary $version): string => $version->comment, $this->versions->history(42, 2, 2)->items));
    }

    public function testHistoryOfEntityWithoutVersionsIsEmpty(): void
    {
        $page = $this->versions->history(42);

        self::assertSame([], $page->items);
        self::assertSame(0, $page->total);
    }

    public function testHistoryRejectsInvalidPaging(): void
    {
        $this->expectException(InvalidValueException::class);

        $page = $this->versions->history(42, limit: 0);
        self::assertSame(0, $page->total);
    }

    public function testGetReturnsStoredValues(): void
    {
        $recorded = $this->versions->record(self::article(), 'Створив');

        $view = $this->versions->get(42, $recorded->id);

        self::assertSame($recorded->id, $view->id);
        self::assertSame('2026-11-16T10:00:00.500000+00:00', $view->fields['publishedAt']);
        self::assertSame(['metaTitle' => 'SEO', 'metaDescription' => null], $view->fields['seo'], 'a relation is an array');
        self::assertSame(
            [
                ['locale' => 'en', 'title' => 'Article', 'attachments' => []],
                ['locale' => 'uk', 'title' => 'Стаття', 'attachments' => []],
            ],
            $view->fields['translations'],
        );
    }

    public function testGetDoesNotRevealVersionOfAnotherEntity(): void
    {
        $foreign = $this->versions->record(self::article(43), 'Чужа');

        $this->expectException(VersionNotFoundException::class);

        $view = $this->versions->get(42, $foreign->id);
        self::assertSame($foreign->id, $view->id);
    }

    public function testGetUnknownVersion(): void
    {
        $this->expectException(VersionNotFoundException::class);

        $view = $this->versions->get(42, '0193a8f2-7c4e-7b21-9f3a-5d2e8c1b4a07');
        self::assertSame(42, $view->entityId);
    }

    // ---- checkout ---------------------------------------------------------------------------------

    public function testCheckoutReturnsCopyWithOldValues(): void
    {
        $article = self::article();
        $first = $this->versions->record($article, 'Перша');

        $article->title = 'Новий заголовок';
        $article->translations = [];
        $this->versions->record($article, 'Друга');

        $old = $this->versions->checkout($article, $first->id);

        self::assertNotSame($article, $old);
        self::assertSame('Стаття', $old->title);
        self::assertCount(2, $old->translations);
        self::assertSame('Новий заголовок', $article->title, 'the current entity stays untouched');
    }

    public function testCheckoutDoesNotRevealVersionOfAnotherEntity(): void
    {
        $foreign = $this->versions->record(self::article(43), 'Чужа');

        $this->expectException(VersionNotFoundException::class);

        $old = $this->versions->checkout(self::article(42), $foreign->id);
        self::assertSame(43, $old->id);
    }

    // ---- removeAll --------------------------------------------------------------------------------

    public function testRemoveAllClearsOnlyThatEntity(): void
    {
        $this->versions->record(self::article(42), 'A');
        $this->versions->record(self::article(42), 'B');
        $this->versions->record(self::article(43), 'C');

        self::assertSame(2, $this->versions->removeAll(42));
        self::assertSame(0, $this->versions->history(42)->total);
        self::assertSame(1, $this->versions->history(43)->total);
    }

    private function service(VersionRepositoryInterface $repository): VersionService
    {
        return new VersionService($repository, $this->authors, $this->clock, new ObjectAccessor());
    }

    private function reference(int $id): EntityReference
    {
        return new EntityReference(EntityType::fromInt(1), EntityId::fromInt($id));
    }

    private static function article(int $id = 42): Article
    {
        $article = new Article();
        $article->id = $id;
        $article->title = 'Стаття';
        $article->status = ArticleStatus::Published;
        $article->publishedAt = new DateTimeImmutable('2026-11-16 10:00:00.5', new DateTimeZone('UTC'));
        $article->price = 9.5;
        $article->seo = Seo::of('SEO', null);
        $article->translations = [Translation::of('en', 'Article'), Translation::of('uk', 'Стаття')];

        return $article;
    }
}
