<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Integration;

use Ocdwefo\VersionService\Application\AuthorResolverInterface;
use Ocdwefo\VersionService\Application\EntityVersions;
use Ocdwefo\VersionService\Application\VersionPruner;
use Ocdwefo\VersionService\Application\VersionService;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Domain\Schema\FieldAccessorInterface;
use Ocdwefo\VersionService\Infrastructure\SystemClock;
use Ocdwefo\VersionService\Infrastructure\Yii2\Console\VersionsController;
use Ocdwefo\VersionService\Infrastructure\Yii2\DbVersionRepository;
use Ocdwefo\VersionService\Infrastructure\Yii2\Migration\M261117000000CreateVersionTable;
use Ocdwefo\VersionService\Infrastructure\Yii2\Schema\ActiveRecordAccessor;
use Ocdwefo\VersionService\Infrastructure\Yii2\YiiUserAuthorResolver;
use Ocdwefo\VersionService\Tests\Support\Yii\ArticleRecord;
use Ocdwefo\VersionService\Tests\Support\Yii\ArticleRecordSchema;
use Ocdwefo\VersionService\Tests\Support\Yii\TranslationRecord;
use Ocdwefo\VersionService\Tests\Support\Yii\YiiApplications;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Yii;
use yii\base\Application;
use yii\db\Connection;
use yii\di\Container;

/**
 * The whole stack on ActiveRecord models with a relation, against a real database.
 *
 * Runs only with VERSION_SERVICE_TEST_DSN (plus _USER and _PASSWORD) pointing to an empty database:
 * the test creates its tables with random names and drops them at the end.
 */
#[CoversClass(ActiveRecordAccessor::class)]
#[CoversClass(VersionsController::class)]
final class ActiveRecordVersioningTest extends TestCase
{
    /** Console has no logged in user: every record() names its author. */
    private const int AUTHOR = 7;

    private static bool $ready = false;

    private static string $versionTable = '';

    private VersionPruner $pruner;

    /** @var EntityVersions<ArticleRecord> */
    private EntityVersions $articles;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dsn = getenv('VERSION_SERVICE_TEST_DSN');
        if (!is_string($dsn) || $dsn === '') {
            return;
        }

        $user = getenv('VERSION_SERVICE_TEST_USER');
        $password = getenv('VERSION_SERVICE_TEST_PASSWORD');

        $suffix = bin2hex(random_bytes(4));
        ArticleRecord::$table = 'vs_article_' . $suffix;
        TranslationRecord::$table = 'vs_translation_' . $suffix;
        self::$versionTable = 'vs_version_' . $suffix;

        $db = YiiApplications::console([
            'dsn' => $dsn,
            'username' => is_string($user) ? $user : '',
            'password' => is_string($password) ? $password : '',
            'charset' => 'utf8mb4',
        ])->getDb();

        $db->createCommand()->createTable(ArticleRecord::$table, [
            'id' => 'pk',
            'title' => 'string NOT NULL',
            'status' => 'integer NOT NULL',
            'published_at' => 'datetime NULL',
        ])->execute();
        $db->createCommand()->createTable(TranslationRecord::$table, [
            'id' => 'pk',
            'article_id' => 'integer NOT NULL',
            'locale' => 'string(5) NOT NULL',
            'title' => 'string NOT NULL',
        ])->execute();

        $migration = new M261117000000CreateVersionTable(['db' => $db, 'compact' => true]);
        $migration->tableName = self::$versionTable;
        ob_start();
        $created = $migration->up();
        $output = (string) ob_get_clean();
        if ($created === false) {
            throw new RuntimeException('The migration failed: ' . $output);
        }

        self::$ready = true;
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        if (self::$ready) {
            $db = self::db();
            foreach ([ArticleRecord::$table, TranslationRecord::$table, self::$versionTable] as $table) {
                $db->createCommand()->dropTable($table)->execute();
            }
            $db->close();
            self::$ready = false;
        }

        YiiApplications::destroy();
        parent::tearDownAfterClass();
    }

    #[Override]
    protected function setUp(): void
    {
        if (!self::$ready) {
            self::markTestSkipped('Set VERSION_SERVICE_TEST_DSN, VERSION_SERVICE_TEST_USER and VERSION_SERVICE_TEST_PASSWORD to run.');
        }

        parent::setUp();

        $db = self::db();
        foreach ([ArticleRecord::$table, TranslationRecord::$table, self::$versionTable] as $table) {
            $db->createCommand()->truncateTable($table)->execute();
        }

        Yii::$container = new Container();
        Yii::$container->setSingletons([
            VersionRepositoryInterface::class => [
                'class' => DbVersionRepository::class,
                '__construct()' => ['db' => 'db', 'table' => self::$versionTable],
            ],
            AuthorResolverInterface::class => YiiUserAuthorResolver::class,
            ClockInterface::class => SystemClock::class,
            FieldAccessorInterface::class => ActiveRecordAccessor::class,
            VersionPruner::class => ['__construct()' => ['keepVersions' => 2, 'schemas' => [ArticleRecordSchema::class]]],
            VersionService::class => VersionService::class,
        ]);

        $service = Yii::$container->get(VersionService::class);
        self::assertInstanceOf(VersionService::class, $service);
        $this->articles = $service->of(new ArticleRecordSchema());

        $pruner = Yii::$container->get(VersionPruner::class);
        self::assertInstanceOf(VersionPruner::class, $pruner);
        $this->pruner = $pruner;
    }

    public function testRecordsAttributesAndRelation(): void
    {
        $article = $this->createArticle('Стаття', ['en' => 'Article', 'uk' => 'Стаття']);

        $view = $this->articles->record($article, 'Створив', authorId: self::AUTHOR);

        self::assertSame($article->id, $view->entityId);
        self::assertSame(self::AUTHOR, $view->authorId, 'console: the author passed explicitly');
        self::assertSame('Стаття', $view->fields['title']);
        self::assertSame(1, $view->fields['status']);
        self::assertSame('2026-11-16 10:00:00', $view->fields['published_at'], 'dates stay strings');
        self::assertSame(
            [['locale' => 'en', 'title' => 'Article'], ['locale' => 'uk', 'title' => 'Стаття']],
            $view->fields['translations'],
            'a relation is stored as plain values',
        );
    }

    public function testCheckoutPutsOldValuesIntoCopyOnly(): void
    {
        $article = $this->createArticle('Стара назва', ['en' => 'Old', 'uk' => 'Стара']);
        $first = $this->articles->record($article, 'Перша', authorId: self::AUTHOR);

        $article->title = 'Нова назва';
        $article->save(false);
        TranslationRecord::deleteAll(['article_id' => $article->id, 'locale' => 'uk']);
        $current = $this->reload($article->id);
        $this->articles->record($current, 'Друга', authorId: self::AUTHOR);

        $old = $this->articles->checkout($current, $first->id);

        self::assertNotSame($current, $old);
        self::assertSame('Стара назва', $old->title);
        self::assertTrue($old->isRelationPopulated('translations'));
        self::assertSame(['en' => 'Old', 'uk' => 'Стара'], self::titles($old->translations));

        self::assertSame('Нова назва', $current->title, 'the current model stays untouched');
        self::assertSame('Нова назва', $this->reload($article->id)->title, 'nothing is written to the database');
        self::assertSame(1, (int) TranslationRecord::find()->where(['article_id' => $article->id])->count());
    }

    public function testRestoreIsCheckoutSavedByTheProject(): void
    {
        $article = $this->createArticle('Стара назва', ['en' => 'Old']);
        $first = $this->articles->record($article, 'Перша', authorId: self::AUTHOR);
        $article->title = 'Нова назва';
        $article->save(false);
        $this->articles->record($article, 'Друга', authorId: self::AUTHOR);

        $restored = $this->articles->checkout($this->reload($article->id), $first->id);
        self::assertTrue($restored->save(false));
        $view = $this->articles->record($restored, 'Відновив версію 1', authorId: self::AUTHOR);

        self::assertSame('Стара назва', $this->reload($article->id)->title);
        self::assertSame(3, $view->number);
    }

    public function testPrunesSchemasListedInTheContainer(): void
    {
        $article = $this->createArticle('Стаття', []);
        foreach (['Перша', 'Друга', 'Третя'] as $comment) {
            $this->articles->record($article, $comment, authorId: self::AUTHOR);
        }

        self::assertSame(1, $this->pruner->prune());
        self::assertSame(2, $this->articles->history($article->id)->total, 'keepVersions = 2');
    }

    /**
     * @param array<string, string> $translations Title by locale.
     */
    private function createArticle(string $title, array $translations): ArticleRecord
    {
        $article = new ArticleRecord();
        $article->title = $title;
        $article->status = 1;
        $article->published_at = '2026-11-16 10:00:00';
        self::assertTrue($article->save(false));

        foreach ($translations as $locale => $translationTitle) {
            $translation = new TranslationRecord();
            $translation->article_id = $article->id;
            $translation->locale = $locale;
            $translation->title = $translationTitle;
            self::assertTrue($translation->save(false));
        }

        return $this->reload($article->id);
    }

    private function reload(int $id): ArticleRecord
    {
        $article = ArticleRecord::findOne($id);
        self::assertInstanceOf(ArticleRecord::class, $article);

        return $article;
    }

    /**
     * @return array<string, string> Title by locale.
     */
    private static function titles(mixed $translations): array
    {
        self::assertIsArray($translations);

        $titles = [];
        foreach ($translations as $translation) {
            self::assertInstanceOf(TranslationRecord::class, $translation);
            $titles[$translation->locale] = $translation->title;
        }

        return $titles;
    }

    private static function db(): Connection
    {
        return self::app()->getDb();
    }

    private static function app(): Application
    {
        return Yii::$app ?? throw new RuntimeException('No Yii application.');
    }
}
