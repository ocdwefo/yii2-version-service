<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

use Closure;
use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;

/**
 * One versioned field of an object: its name, type and how to reach it.
 *
 * Immutable: modifiers return a new field.
 *
 * ```php
 * Field::string('title'),
 * Field::string('published_at')->nullable(),
 * Field::collection('translations', TranslationSchema::class),
 * ```
 */
final readonly class Field
{
    private const string NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @param class-string<RelationSchema<covariant object>>|null $schemaClass For relations.
     * @param Closure|null $reader Custom read: fn (object $owner): mixed.
     * @param Closure|null $writer Custom write: fn (object $owner, mixed $value): void.
     */
    private function __construct(
        public string $name,
        public FieldType $type,
        public bool $nullable = false,
        public ?string $schemaClass = null,
        public ?Closure $reader = null,
        public ?Closure $writer = null,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidSchemaException(sprintf('"%s" is not a valid field name.', $name));
        }
    }

    public static function string(string $name): self
    {
        return new self($name, FieldType::String);
    }

    public static function int(string $name): self
    {
        return new self($name, FieldType::Int);
    }

    /**
     * Accepts int values too, they are stored as float.
     */
    public static function float(string $name): self
    {
        return new self($name, FieldType::Float);
    }

    public static function bool(string $name): self
    {
        return new self($name, FieldType::Bool);
    }

    public static function array(string $name): self
    {
        return new self($name, FieldType::Array);
    }

    /**
     * One related object (one-to-one).
     *
     * @param class-string<RelationSchema<covariant object>> $schemaClass
     */
    public static function object(string $name, string $schemaClass): self
    {
        return new self($name, FieldType::Object, schemaClass: self::assertSchemaClass($name, $schemaClass));
    }

    /**
     * List of related objects (one-to-many). Never null: no items is an empty list.
     *
     * @param class-string<RelationSchema<covariant object>> $schemaClass
     */
    public static function collection(string $name, string $schemaClass): self
    {
        return new self($name, FieldType::Collection, schemaClass: self::assertSchemaClass($name, $schemaClass));
    }

    /**
     * The field may hold null, which then means "no value".
     */
    public function nullable(): self
    {
        if ($this->type === FieldType::Collection) {
            throw new InvalidSchemaException(sprintf(
                'Collection "%s" cannot be nullable, an empty list means "no items".',
                $this->name,
            ));
        }

        return $this->with(nullable: true);
    }

    /**
     * Reads the value with the closure instead of the accessor.
     *
     * @param Closure $reader fn (object $owner): mixed
     */
    public function readUsing(Closure $reader): self
    {
        return $this->with(reader: $reader);
    }

    /**
     * Writes the value with the closure instead of the accessor.
     *
     * @param Closure $writer fn (object $owner, mixed $value): void
     */
    public function writeUsing(Closure $writer): self
    {
        return $this->with(writer: $writer);
    }

    private function with(?bool $nullable = null, ?Closure $reader = null, ?Closure $writer = null): self
    {
        return new self(
            $this->name,
            $this->type,
            $nullable ?? $this->nullable,
            $this->schemaClass,
            $reader ?? $this->reader,
            $writer ?? $this->writer,
        );
    }

    /**
     * @return class-string<RelationSchema<covariant object>>
     */
    private static function assertSchemaClass(string $name, string $schemaClass): string
    {
        if (!is_subclass_of($schemaClass, RelationSchema::class)) {
            throw new InvalidSchemaException(sprintf(
                'Field "%s": %s does not extend %s.',
                $name,
                $schemaClass,
                RelationSchema::class,
            ));
        }

        return $schemaClass;
    }
}
