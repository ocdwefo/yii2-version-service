<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;

/**
 * Id of the user who made the version. Every version has one: the logged in user, or the author the caller
 * names where nobody is logged in. The package does not know the user model of the project.
 */
final readonly class AuthorId
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
            throw new InvalidValueException(sprintf('Author id must be positive, %d given.', $value));
        }

        return new self($value);
    }
}
