<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Schema;

use DateTimeImmutable;
use DateTimeZone;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\Snapshot;
use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\ObjectAccessor;
use Ocdwefo\VersionService\Domain\Schema\SchemaRegistry;
use Ocdwefo\VersionService\Domain\Schema\SnapshotMapper;
use Ocdwefo\VersionService\Tests\Support\Blog\Article;
use Ocdwefo\VersionService\Tests\Support\Blog\ArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\ArticleStatus;
use Ocdwefo\VersionService\Tests\Support\Blog\Attachment;
use Ocdwefo\VersionService\Tests\Support\Blog\ConfigurableArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\LegacyDocument;
use Ocdwefo\VersionService\Tests\Support\Blog\LegacyDocumentSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\Seo;
use Ocdwefo\VersionService\Tests\Support\Blog\Translation;
use Ocdwefo\VersionService\Tests\Support\Blog\TranslationSchema;
use Ocdwefo\VersionService\Tests\Support\Blog\TreeNode;
use Ocdwefo\VersionService\Tests\Support\Blog\TreeRootSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SnapshotMapper::class)]
#[CoversClass(SchemaRegistry::class)]
final class SnapshotMapperTest extends TestCase
{
    private SnapshotMapper $mapper;

    private ArticleSchema $schema;

    #[\Override]
    protected function setUp(): void
    {
        $this->mapper = new SnapshotMapper(new ObjectAccessor());
        $this->schema = new ArticleSchema();
    }

    // ---- entity id -------------------------------------------------------------------------------

    public function testEntityIdIsReadFromIdField(): void
    {
        $article = new Article();
        $article->id = 42;

        self::assertSame(42, $this->mapper->entityId($this->schema, $article));
    }

    public function testEntityIdMustBeInt(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Override ' . LegacyDocumentSchema::class . '::idField()');

        $this->mapper->entityId(new LegacyDocumentSchema(), new LegacyDocument());
    }

    // ---- extract ----------------------------------------------------------------------------------

    public function testExtractsEveryFieldTypeAndRelation(): void
    {
        $snapshot = $this->mapper->extract($this->schema, self::article());

        self::assertSame(
            [
                'title' => 'Стаття',
                'status' => 'published',
                'publishedAt' => '2026-11-16T10:00:00.500000+00:00',
                'price' => 9.5,
                'featured' => true,
                'tags' => ['php', 'yii'],
                'seo' => ['metaTitle' => 'SEO заголовок', 'metaDescription' => null],
                'translations' => [
                    ['locale' => 'en', 'title' => 'Article', 'attachments' => [['name' => 'a.pdf', 'size' => 1024]]],
                    ['locale' => 'uk', 'title' => 'Стаття', 'attachments' => []],
                ],
            ],
            $snapshot->data,
        );
    }

    public function testFieldsOutsideTheSchemaAreNotVersioned(): void
    {
        $article = self::article();
        $before = $this->mapper->extract($this->schema, $article);

        $article->views = 1000;
        $article->id = 99;

        self::assertSame($before->data, $this->mapper->extract($this->schema, $article)->data);
    }

    public function testIntIsAcceptedAsFloat(): void
    {
        $article = self::article();
        $article->price = 10;

        self::assertSame(10.0, $this->mapper->extract($this->schema, $article)->data['price']);
    }

    public function testNullableFieldsMayBeNull(): void
    {
        $article = self::article();
        $article->publishedAt = null;
        $article->seo = null;

        $data = $this->mapper->extract($this->schema, $article)->data;

        self::assertNull($data['publishedAt']);
        self::assertNull($data['seo']);
    }

    public function testRejectsNullInNotNullableField(): void
    {
        $schema = new ConfigurableArticleSchema([
            Field::string('title')->readUsing(static fn (Article $article): ?string => null),
        ]);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Field "title" must not be null');

        $this->mapper->extract($schema, self::article());
    }

    /**
     * @return iterable<string, array{Field, string}>
     */
    public static function wrongValues(): iterable
    {
        yield 'string' => [Field::string('v')->readUsing(static fn (): int => 1), 'must be string, int given'];
        yield 'int' => [Field::int('v')->readUsing(static fn (): string => '1'), 'must be int, string given'];
        yield 'float' => [Field::float('v')->readUsing(static fn (): string => '9.5'), 'must be float, string given'];
        yield 'bool' => [Field::bool('v')->readUsing(static fn (): int => 1), 'must be bool, int given'];
        yield 'array' => [Field::array('v')->readUsing(static fn (): string => 'a,b'), 'must be array, string given'];
        yield 'object' => [Field::object('v', TranslationSchema::class)->readUsing(static fn (): Seo => new Seo()), 'must be ' . Translation::class];
        yield 'collection' => [Field::collection('v', TranslationSchema::class)->readUsing(static fn (): string => 'x'), 'must be iterable'];
    }

    #[DataProvider('wrongValues')]
    public function testRejectsValueOfWrongType(Field $field, string $message): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage($message);

        $this->mapper->extract(new ConfigurableArticleSchema([$field]), self::article());
    }

    public function testErrorPathPointsIntoNestedRelation(): void
    {
        $article = self::article();
        /** @phpstan-ignore assign.propertyType (the point of the test) */
        $article->translations[1]->attachments = [Attachment::of('a.pdf', 1), Seo::of('x', null)];

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Value at "translations.1.attachments.1" must be ' . Attachment::class);

        $this->mapper->extract($this->schema, $article);
    }

    public function testRejectsEntityOfAnotherClass(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage(Article::class);

        /** @phpstan-ignore argument.type (the point of the test) */
        $this->mapper->extract($this->schema, new Translation());
    }

    public function testCustomReaderComputesValue(): void
    {
        $schema = new ConfigurableArticleSchema([
            Field::string('slug')->readUsing(static fn (Article $article): string => mb_strtolower($article->title)),
        ]);

        self::assertSame(['slug' => 'стаття'], $this->mapper->extract($schema, self::article())->data);
    }

    public function testSchemaMayContainItself(): void
    {
        $root = self::tree('a', 'b', 'c');

        $snapshot = $this->mapper->extract(new TreeRootSchema(), $root);

        self::assertSame(
            ['name' => 'a', 'child' => ['name' => 'b', 'child' => ['name' => 'c', 'child' => null]]],
            $snapshot->data,
        );

        $restored = $this->mapper->hydrate(new TreeRootSchema(), new TreeNode(), $snapshot);
        self::assertSame('c', $restored->child?->child?->name);
    }

    public function testRejectsObjectContainingItself(): void
    {
        $root = self::tree('a', 'b');
        self::assertNotNull($root->child);
        $root->child->child = $root->child;

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Value at "child.child" is an object that contains itself');

        $this->mapper->extract(new TreeRootSchema(), $root);
    }

    // ---- hydrate ----------------------------------------------------------------------------------

    public function testHydrateReturnsCopyWithStoredValues(): void
    {
        $old = self::article();
        $snapshot = $this->mapper->extract($this->schema, $old);

        $current = self::article();
        $current->title = 'Новий заголовок';
        $current->status = ArticleStatus::Draft;
        $current->publishedAt = null;
        $current->price = 20.0;
        $current->featured = false;
        $current->tags = ['changed'];
        $current->seo = null;
        $current->translations = [Translation::of('pl', 'Artykuł')];
        $current->views = 500;

        $restored = $this->mapper->hydrate($this->schema, $current, $snapshot);

        self::assertNotSame($current, $restored);
        self::assertSame('Новий заголовок', $current->title, 'the current entity stays untouched');

        self::assertSame('Стаття', $restored->title);
        self::assertSame(ArticleStatus::Published, $restored->status);
        self::assertEquals(new DateTimeImmutable('2026-11-16 10:00:00.5 UTC'), $restored->publishedAt);
        self::assertSame(9.5, $restored->price);
        self::assertTrue($restored->featured);
        self::assertSame(['php', 'yii'], $restored->tags);
        self::assertSame(500, $restored->views, 'fields outside the schema keep the current value');

        self::assertInstanceOf(Seo::class, $restored->seo);
        self::assertSame('SEO заголовок', $restored->seo->getMetaTitle());
        self::assertNull($restored->seo->getMetaDescription());

        self::assertCount(2, $restored->translations);
        self::assertSame('en', $restored->translations[0]->locale);
        self::assertSame('Article', $restored->translations[0]->title);
        self::assertCount(1, $restored->translations[0]->attachments);
        self::assertSame('a.pdf', $restored->translations[0]->attachments[0]->name);
        self::assertSame(1024, $restored->translations[0]->attachments[0]->size);
        self::assertSame('uk', $restored->translations[1]->locale);
    }

    public function testRoundTripKeepsTheSnapshot(): void
    {
        $snapshot = $this->mapper->extract($this->schema, self::article());

        $restored = $this->mapper->hydrate($this->schema, new Article(), $snapshot);

        self::assertSame($snapshot->data, $this->mapper->extract($this->schema, $restored)->data);
    }

    public function testFieldMissingInOldVersionKeepsCurrentValue(): void
    {
        $current = self::article();

        $restored = $this->mapper->hydrate($this->schema, $current, Snapshot::fromArray(['title' => 'Стара назва']));

        self::assertSame('Стара назва', $restored->title);
        self::assertSame(ArticleStatus::Published, $restored->status);
        self::assertCount(2, $restored->translations);
    }

    public function testStoredFieldNoLongerInSchemaIsIgnored(): void
    {
        $restored = $this->mapper->hydrate(
            $this->schema,
            self::article(),
            Snapshot::fromArray(['title' => 'Стара назва', 'subtitle' => 'Поле, якого вже немає']),
        );

        self::assertSame('Стара назва', $restored->title);
    }

    public function testCollectionIsReplacedAsAWhole(): void
    {
        $restored = $this->mapper->hydrate(
            $this->schema,
            self::article(),
            Snapshot::fromArray(['translations' => [['locale' => 'pl', 'title' => 'Artykuł', 'attachments' => []]]]),
        );

        self::assertCount(1, $restored->translations);
        self::assertSame('pl', $restored->translations[0]->locale);
    }

    public function testStoredIntIsAcceptedAsFloat(): void
    {
        $restored = $this->mapper->hydrate($this->schema, self::article(), Snapshot::fromArray(['price' => 10]));

        self::assertSame(10.0, $restored->price);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function incompatibleStoredValues(): iterable
    {
        yield 'null in not nullable' => [['title' => null], 'is null, but the field is not nullable'];
        yield 'wrong type' => [['price' => 'cheap'], 'Stored value of field "price" must be float'];
        yield 'collection is not a list' => [['translations' => ['en' => []]], 'must be list'];
        yield 'nested wrong type' => [['translations' => [['locale' => 'en', 'title' => 5]]], 'field "translations.0.title" must be string'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('incompatibleStoredValues')]
    public function testRejectsIncompatibleStoredValue(array $data, string $message): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage($message);

        $this->mapper->hydrate($this->schema, self::article(), Snapshot::fromArray($data));
    }

    public function testCustomWriterReceivesTypedValue(): void
    {
        $schema = new ConfigurableArticleSchema([
            Field::string('headline')
                ->readUsing(static fn (Article $article): string => $article->title)
                ->writeUsing(static function (Article $article, mixed $value): void {
                    $article->title = 'headline: ' . (is_string($value) ? $value : '');
                }),
        ]);

        $restored = $this->mapper->hydrate($schema, self::article(), Snapshot::fromArray(['headline' => 'Стара']));

        self::assertSame('headline: Стара', $restored->title);
    }

    private static function tree(string ...$names): TreeNode
    {
        $root = null;
        foreach (array_reverse($names) as $name) {
            $node = new TreeNode();
            $node->name = $name;
            $node->child = $root;
            $root = $node;
        }
        self::assertNotNull($root);

        return $root;
    }

    private static function article(): Article
    {
        $article = new Article();
        $article->id = 42;
        $article->title = 'Стаття';
        $article->status = ArticleStatus::Published;
        $article->publishedAt = new DateTimeImmutable('2026-11-16 10:00:00.5', new DateTimeZone('UTC'));
        $article->price = 9.5;
        $article->featured = true;
        $article->tags = ['php', 'yii'];
        $article->seo = Seo::of('SEO заголовок', null);
        $article->translations = [
            Translation::of('en', 'Article', [Attachment::of('a.pdf', 1024)]),
            Translation::of('uk', 'Стаття'),
        ];

        return $article;
    }
}
