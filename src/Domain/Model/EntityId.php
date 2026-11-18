<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;

/**
 * Primary key of the versioned entity.
 */
final readonly class EntityId
{
    private function __construct(public int $value)
    {
    }

    /**
     * @throws InvalidValueException When the id is not positive.
     */
    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw new InvalidValueException(sprintf('Entity id must be positive, %d given.', $value));
        }

        return new self($value);
    }
}
