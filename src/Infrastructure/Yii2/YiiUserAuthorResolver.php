<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Infrastructure\Yii2;

use Ocdwefo\VersionService\Application\AuthorResolverInterface;
use Ocdwefo\VersionService\Domain\Exception\UnknownAuthorException;
use Override;
use Yii;
use yii\base\InvalidConfigException;
use yii\console\Application as ConsoleApplication;
use yii\web\User;

/**
 * The logged in user of the Yii `user` component.
 *
 * Without one (console, cron, no application, a guest) there is no author to guess: the caller passes
 * it to record() as authorId. A web application without the component is a configuration error.
 *
 * ```php
 * AuthorResolverInterface::class => YiiUserAuthorResolver::class,
 * ```
 */
final readonly class YiiUserAuthorResolver implements AuthorResolverInterface
{
    /**
     * @param string $component Application component holding the logged in user.
     */
    public function __construct(
        private string $component = 'user',
    ) {
    }

    #[Override]
    public function current(): int
    {
        $app = Yii::$app;
        if ($app === null) {
            throw UnknownAuthorException::nobodyLoggedIn('no application');
        }

        if ($app instanceof ConsoleApplication) {
            throw UnknownAuthorException::nobodyLoggedIn('console application');
        }

        if (!$app->has($this->component)) {
            throw new InvalidConfigException(sprintf('Application has no "%s" component to take the author from.', $this->component));
        }

        $user = $app->get($this->component);
        if (!$user instanceof User) {
            throw new InvalidConfigException(sprintf('Component "%s" must be %s, %s given.', $this->component, User::class, get_debug_type($user)));
        }

        $id = $user->getId();
        if ($id === null) {
            throw UnknownAuthorException::nobodyLoggedIn('guest');
        }

        if (!is_int($id)) {
            throw new InvalidConfigException(sprintf(
                'Id of the logged in user must be an int, %s given: make getId() of %s return int, or bind your own %s.',
                get_debug_type($id),
                get_debug_type($user->getIdentity()),
                AuthorResolverInterface::class,
            ));
        }

        return $id;
    }
}
