<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Yii;

use Yii;
use yii\console\Application as ConsoleApplication;
use yii\db\Connection;
use yii\di\Container;
use yii\web\Application as WebApplication;
use yii\web\User;

/**
 * Creates and destroys Yii applications for tests.
 */
final class YiiApplications
{
    /**
     * @param array<string, mixed> $db Config of the "db" component, never connected unless used.
     */
    public static function console(array $db = []): ConsoleApplication
    {
        return new ConsoleApplication([
            'id' => 'version-service-test',
            'basePath' => self::basePath(),
            'components' => ['db' => $db + ['class' => Connection::class, 'dsn' => 'mysql:host=127.0.0.1;dbname=unused']],
        ]);
    }

    public static function web(): WebApplication
    {
        return new WebApplication([
            'id' => 'version-service-test',
            'basePath' => self::basePath(),
            'components' => [
                'db' => ['class' => Connection::class, 'dsn' => 'mysql:host=127.0.0.1;dbname=unused'],
                'user' => ['class' => User::class, 'identityClass' => TestIdentity::class, 'enableSession' => false],
                'request' => ['cookieValidationKey' => 'test'],
            ],
        ]);
    }

    public static function destroy(): void
    {
        Yii::$app = null;
        Yii::$container = new Container();
    }

    private static function basePath(): string
    {
        return dirname(__DIR__, 3);
    }
}
