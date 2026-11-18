<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Exception;

/**
 * Nobody is making the change: no logged in user and no author passed to record().
 * Code running without a user (console, cron, import) says on whose behalf it acts.
 */
final class UnknownAuthorException extends VersioningException
{
    public static function nobodyLoggedIn(string $where): self
    {
        return new self(sprintf('Nobody is logged in (%s): pass the author to record() as authorId.', $where));
    }
}
