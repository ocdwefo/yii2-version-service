<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use BackedEnum;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;

/**
 * Kind of the versioned entity, stored as a small integer.
 *
 * The project lists its versioned entities in one int backed enum, which keeps the codes unique:
 *
 * ```php
 * enum VersionType: int
 * {
 *     case Article = 1;
 *     case Page = 2;
 * }
 * ```
 *
 * A code must never change or be given to another entity once versions of it are stored.
 */
final readonly class EntityType
{
    /** Upper bound of the SMALLINT UNSIGNED column. */
    public const int MAX = 65535;

    private function __construct(public int $value)
    {
    }

    /**
     * @throws InvalidValueException When the enum is not int backed or its value is out of range.
     */
    public static function fromEnum(BackedEnum $case): self
    {
        if (!is_int($case->value)) {
            throw new InvalidValueException(sprintf(
                'Entity type must be a case of an int backed enum, %s::%s is backed by a string.',
                $case::class,
                $case->name,
            ));
        }

        return self::fromInt($case->value);
    }

    /**
     * @throws InvalidValueException When the code is out of range.
     */
    public static function fromInt(int $value): self
    {
        if ($value < 1 || $value > self::MAX) {
            throw new InvalidValueException(sprintf('Entity type must be between 1 and %d, %d given.', self::MAX, $value));
        }

        return new self($value);
    }
}
