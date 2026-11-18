<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Infrastructure\Yii2;

use Ocdwefo\VersionService\Application\AuthorResolverInterface;
use Ocdwefo\VersionService\Application\VersionPruner;
use Ocdwefo\VersionService\Application\VersionService;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Domain\Schema\FieldAccessorInterface;
use Ocdwefo\VersionService\Domain\Schema\ObjectAccessor;
use Ocdwefo\VersionService\Infrastructure\Persistence\InMemoryVersionRepository;
use Ocdwefo\VersionService\Infrastructure\SystemClock;
use Ocdwefo\VersionService\Infrastructure\Yii2\DbVersionRepository;
use Ocdwefo\VersionService\Infrastructure\Yii2\Schema\ActiveRecordAccessor;
use Ocdwefo\VersionService\Infrastructure\Yii2\YiiUserAuthorResolver;
use Ocdwefo\VersionService\Tests\Support\Blog\Article;
use Ocdwefo\VersionService\Tests\Support\Blog\ArticleSchema;
use Ocdwefo\VersionService\Tests\Support\Yii\YiiApplications;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Yii;
use yii\base\InvalidConfigException;

/**
 * The container configuration given in the README builds the service, lazily.
 */
#[CoversNothing]
final class ContainerWiringTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        YiiApplications::console(); // the "db" component, never connected here
    }

    #[Override]
    protected function tearDown(): void
    {
        YiiApplications::destroy();
        parent::tearDown();
    }

    public function testReadmeConfigurationBuildsOneService(): void
    {
        Yii::$container->setSingletons(self::definitions(keepVersions: 20));

        $service = Yii::$container->get(VersionService::class);

        self::assertInstanceOf(VersionService::class, $service);
        self::assertSame($service, Yii::$container->get(VersionService::class));
        self::assertInstanceOf(DbVersionRepository::class, Yii::$container->get(VersionRepositoryInterface::class));
        self::assertInstanceOf(ActiveRecordAccessor::class, Yii::$container->get(FieldAccessorInterface::class));
        self::assertInstanceOf(VersionPruner::class, Yii::$container->get(VersionPruner::class));

        self::assertInstanceOf(YiiUserAuthorResolver::class, Yii::$container->get(AuthorResolverInterface::class));
    }

    public function testAccessorMustBeConfigured(): void
    {
        $definitions = self::definitions(keepVersions: 20);
        unset($definitions[FieldAccessorInterface::class]);
        Yii::$container->setSingletons($definitions);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(FieldAccessorInterface::class);

        Yii::$container->get(VersionService::class);
    }

    public function testAccessorOfProjectWithoutActiveRecord(): void
    {
        Yii::$container->setSingletons([
            ...self::definitions(keepVersions: 20),
            VersionRepositoryInterface::class => InMemoryVersionRepository::class,
            FieldAccessorInterface::class => ObjectAccessor::class,
        ]);

        $service = Yii::$container->get(VersionService::class);
        self::assertInstanceOf(VersionService::class, $service);

        $article = new Article();
        $article->id = 42;
        $article->title = 'Стаття';

        self::assertSame('Стаття', $service->of(new ArticleSchema())->record($article, 'Створив', authorId: 1)->fields['title']);
    }

    // An invalid setting does not break registering: it fails when the part is first asked for.

    public function testInvalidLimitFailsWhenPrunerIsRequested(): void
    {
        Yii::$container->setSingletons(self::definitions(keepVersions: 0));

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('latest one is never removed');

        Yii::$container->get(VersionPruner::class);
    }

    /**
     * @return array<string, mixed>
     */
    private static function definitions(int $keepVersions): array
    {
        return [
            VersionRepositoryInterface::class => DbVersionRepository::class,
            AuthorResolverInterface::class => YiiUserAuthorResolver::class,
            ClockInterface::class => SystemClock::class,
            FieldAccessorInterface::class => ActiveRecordAccessor::class,
            VersionPruner::class => [
                '__construct()' => ['keepVersions' => $keepVersions, 'schemas' => [ArticleSchema::class]],
            ],
            VersionService::class => VersionService::class,
        ];
    }
}
