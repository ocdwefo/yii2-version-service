<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Application;

use Ocdwefo\VersionService\Domain\Exception\UnknownAuthorException;

/**
 * Tells who is making the change right now (port). The package does not know the user model of the project.
 *
 * Asked only when record() gets no authorId. When nobody is making the change (console, cron, a guest)
 * there is no author to guess, so it throws instead of signing the version with someone made up.
 */
interface AuthorResolverInterface
{
    /**
     * @throws UnknownAuthorException When nobody is logged in.
     */
    public function current(): int;
}
