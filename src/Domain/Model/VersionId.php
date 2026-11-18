<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use DateTimeInterface;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;

/**
 * Identity of a version: UUID version 7 (RFC 9562).
 *
 * The first 48 bits are the creation time in milliseconds, so identifiers made later sort after
 * the earlier ones and database indexes grow at the end like with an auto increment.
 * The rest is random. Identifiers made within the same millisecond are unique but not ordered,
 * the order of versions is given by {@see VersionNumber}.
 */
final readonly class VersionId
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private function __construct(public string $value)
    {
    }

    /**
     * @param DateTimeInterface $at Creation time of the version, so the id and the version share one moment.
     */
    public static function generate(DateTimeInterface $at): self
    {
        $milliseconds = (int) $at->format('Uv');

        // 6 bytes of time (big endian) + 10 random bytes.
        $bytes = substr(pack('J', $milliseconds), 2) . random_bytes(10);

        // Version 7 in the high nibble of byte 6, RFC 9562 variant (10xx) in the high bits of byte 8.
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return new self(sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ));
    }

    /**
     * @throws InvalidValueException When the value is not a UUID version 7.
     */
    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidValueException(sprintf('"%s" is not a UUID version 7.', $value));
        }

        return new self($normalized);
    }
}
