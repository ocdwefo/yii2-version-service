<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support;

use Ocdwefo\VersionService\Application\AuthorResolverInterface;
use Override;

/**
 * The author given by the test.
 */
final class StaticAuthorResolver implements AuthorResolverInterface
{
    public function __construct(public int $current = 1)
    {
    }

    #[Override]
    public function current(): int
    {
        return $this->current;
    }
}
