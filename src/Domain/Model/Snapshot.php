<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;

/**
 * State of an entity at a moment of time: field name => plain value.
 *
 * Plain values are string (valid UTF-8), int, float (finite), bool, null and arrays of plain values,
 * i.e. exactly what JSON stores without loss. Objects (dates, enums, related models) are turned into
 * plain values by the schema before they get here.
 */
final readonly class Snapshot
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(public array $data)
    {
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidValueException When the data is empty, is not keyed by field names or holds a non plain value.
     */
    public static function fromArray(array $data): self
    {
        if ($data === []) {
            throw new InvalidValueException('Snapshot must contain at least one field.');
        }

        foreach ($data as $field => $value) {
            if (!is_string($field) || $field === '') {
                throw new InvalidValueException(sprintf('Snapshot must be keyed by field names, key "%s" given.', $field));
            }

            self::assertPlain($value, self::assertUtf8($field, $field));
        }

        /** @var array<string, mixed> $data */
        return new self($data);
    }

    private static function assertPlain(mixed $value, string $path): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                self::assertPlain($item, $path . '.' . self::assertUtf8((string) $key, $path));
            }

            return;
        }

        if (is_string($value)) {
            self::assertUtf8($value, $path);

            return;
        }

        if (is_float($value) && !is_finite($value)) {
            throw new InvalidValueException(sprintf('Snapshot value at "%s" must be a finite number.', $path));
        }

        if ($value === null || is_int($value) || is_float($value) || is_bool($value)) {
            return;
        }

        throw new InvalidValueException(sprintf(
            'Snapshot value at "%s" must be a plain value (string, int, float, bool, null or array), %s given.',
            $path,
            get_debug_type($value),
        ));
    }

    /**
     * JSON holds only UTF-8: anything else would fail later, when the version is stored.
     */
    private static function assertUtf8(string $value, string $path): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidValueException(sprintf('Snapshot value at "%s" must be valid UTF-8.', $path));
        }

        return $value;
    }
}
