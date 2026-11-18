<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;

/**
 * Why the version was made. Required: not empty once surrounding whitespace is trimmed,
 * at most {@see self::MAX_LENGTH} characters (characters, not bytes).
 */
final readonly class Comment
{
    public const int MAX_LENGTH = 255;

    private function __construct(public string $value)
    {
    }

    /**
     * @throws InvalidValueException When the comment is empty, too long or not valid UTF-8.
     */
    public static function fromString(string $value): self
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidValueException('Comment must be a valid UTF-8 string.');
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidValueException('Comment is required.');
        }

        $length = mb_strlen($trimmed, 'UTF-8');
        if ($length > self::MAX_LENGTH) {
            throw new InvalidValueException(sprintf(
                'Comment must not be longer than %d characters, %d given.',
                self::MAX_LENGTH,
                $length,
            ));
        }

        return new self($trimmed);
    }
}
