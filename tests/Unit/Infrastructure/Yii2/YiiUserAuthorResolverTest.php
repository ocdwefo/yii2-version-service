<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Infrastructure\Yii2;

use Ocdwefo\VersionService\Domain\Exception\UnknownAuthorException;
use Ocdwefo\VersionService\Infrastructure\Yii2\YiiUserAuthorResolver;
use Ocdwefo\VersionService\Tests\Support\Yii\TestIdentity;
use Ocdwefo\VersionService\Tests\Support\Yii\YiiApplications;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use yii\base\InvalidConfigException;
use yii\web\User;

#[CoversClass(YiiUserAuthorResolver::class)]
final class YiiUserAuthorResolverTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        YiiApplications::destroy();
        parent::tearDown();
    }

    public function testNoAuthorWithoutApplication(): void
    {
        YiiApplications::destroy();

        $this->expectException(UnknownAuthorException::class);
        $this->expectExceptionMessage('no application');

        new YiiUserAuthorResolver()->current();
    }

    public function testNoAuthorInConsole(): void
    {
        YiiApplications::console();

        $this->expectException(UnknownAuthorException::class);
        $this->expectExceptionMessage('pass the author to record() as authorId');

        new YiiUserAuthorResolver()->current();
    }

    public function testNoAuthorForGuest(): void
    {
        YiiApplications::web();

        $this->expectException(UnknownAuthorException::class);
        $this->expectExceptionMessage('guest');

        new YiiUserAuthorResolver()->current();
    }

    public function testLoggedInUser(): void
    {
        $this->logIn(YiiApplications::web()->getUser(), 5);

        self::assertSame(5, new YiiUserAuthorResolver()->current());
    }

    #[TestWith(['7'])]
    #[TestWith(['admin'])]
    public function testIdMustBeInt(string $id): void
    {
        $this->logIn(YiiApplications::web()->getUser(), $id);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('must be an int, string given');

        new YiiUserAuthorResolver()->current();
    }

    public function testMissingComponentIsConfigurationError(): void
    {
        YiiApplications::web();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('"account"');

        new YiiUserAuthorResolver('account')->current();
    }

    public function testComponentMustBeUser(): void
    {
        YiiApplications::web();

        $this->expectException(InvalidConfigException::class);

        new YiiUserAuthorResolver('request')->current();
    }

    private function logIn(User $user, int|string $id): void
    {
        $user->setIdentity(new TestIdentity($id));
    }
}
