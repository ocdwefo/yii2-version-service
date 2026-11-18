<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Current time in UTC.
 */
final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
