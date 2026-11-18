<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;

/**
 * How many of the newest versions of an entity survive pruning.
 *
 * At least one: the latest version of an entity is never removed, so version numbers never repeat.
 * There is no "keep everything": versioning is not a log, pruning is skipped only by not running it.
 */
final readonly class RetentionLimit
{
    private function __construct(public int $value)
    {
    }

    /**
     * @throws InvalidValueException When the limit is less than one.
     */
    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw new InvalidValueException(sprintf(
                'At least one version must be kept, the latest one is never removed; %d given.',
                $value,
            ));
        }

        return new self($value);
    }
}
