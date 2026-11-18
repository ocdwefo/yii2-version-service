<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;

/**
 * Human readable number of a version within the history of one entity: 1, 2, 3...
 * Numbers of an entity never repeat, the retention policy always keeps the latest version.
 */
final readonly class VersionNumber
{
    private function __construct(public int $value)
    {
    }

    public static function first(): self
    {
        return new self(1);
    }

    /**
     * @throws InvalidValueException When the number is not positive.
     */
    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw new InvalidValueException(sprintf('Version number must be positive, %d given.', $value));
        }

        return new self($value);
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }
}
