<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support;

use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Clock standing still until moved explicitly.
 */
final class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public static function at(string $moment): self
    {
        return new self(new DateTimeImmutable($moment));
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->add(new DateInterval($interval));
    }
}
