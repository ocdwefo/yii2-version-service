<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\Snapshot;

/**
 * Domain service mapping an object to a snapshot and back, field by field, following the schema tree.
 *
 * - {@see self::extract()}: object → plain values, typed and checked against the schema.
 * - {@see self::hydrate()}: snapshot → a COPY of the given object with the stored values;
 *   the root object is never created, only related objects are (by their schema's create()).
 *
 * Old versions stay readable after the schema changes: a field missing in the snapshot keeps
 * the current value, a stored field the schema no longer has is ignored, a collection is replaced as a whole.
 *
 * A schema may contain itself (replies of a comment, subcategories): the walk follows the data, which ends.
 * Objects referring back to an object on their own path are rejected instead of looping forever.
 */
final class SnapshotMapper
{
    private readonly SchemaRegistry $schemas;

    public function __construct(
        private readonly FieldAccessorInterface $accessor,
    ) {
        $this->schemas = new SchemaRegistry();
    }

    /**
     * Primary key of the entity, read from the field named by {@see RootSchema::idField()}.
     *
     * @template T of object
     *
     * @param RootSchema<T> $schema
     * @param T $entity
     *
     * @throws InvalidSchemaException When the entity has no readable id field.
     * @throws InvalidValueException When the id is not an int.
     */
    public function entityId(RootSchema $schema, object $entity): int
    {
        $field = $schema->idField();
        $id = $this->accessor->read($entity, $field);
        if (!is_int($id)) {
            throw new InvalidValueException(sprintf(
                'Id "%s" of %s must be an int, %s given. Override %s::idField() if the key is named differently.',
                $field,
                $entity::class,
                get_debug_type($id),
                $schema::class,
            ));
        }

        return $id;
    }

    /**
     * @template T of object
     *
     * @param RootSchema<T> $schema
     * @param T $entity
     *
     * @throws InvalidSchemaException
     * @throws InvalidValueException When a value does not match its field.
     */
    public function extract(RootSchema $schema, object $entity): Snapshot
    {
        $this->assertInstance($schema, $entity, 'entity');

        return Snapshot::fromArray($this->extractObject($schema, $entity, '', []));
    }

    /**
     * @template T of object
     *
     * @param RootSchema<T> $schema
     * @param T $entity Current entity, it stays untouched.
     *
     * @return T Copy of the entity with the values of the snapshot.
     *
     * @throws InvalidSchemaException
     * @throws InvalidValueException When a stored value does not match its field any more.
     */
    public function hydrate(RootSchema $schema, object $entity, Snapshot $snapshot): object
    {
        $this->assertInstance($schema, $entity, 'entity');

        $copy = clone $entity;
        $this->hydrateObject($schema, $copy, $snapshot->data, '');

        return $copy;
    }

    /**
     * @param Schema<covariant object> $schema
     */
    private function assertInstance(Schema $schema, mixed $value, string $path): object
    {
        $class = $schema->entityClass();
        if (!$value instanceof $class) {
            throw new InvalidValueException(sprintf(
                'Value at "%s" must be %s, %s given.',
                $path,
                $class,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    // ---- extract ----------------------------------------------------------------------------------

    /**
     * @param Schema<covariant object> $schema
     * @param list<object> $path Objects from the root down to this one, to catch an object containing itself.
     *
     * @return array<string, mixed>
     */
    private function extractObject(Schema $schema, object $object, string $prefix, array $path): array
    {
        if (in_array($object, $path, true)) {
            throw new InvalidValueException(sprintf(
                'Value at "%s" is an object that contains itself: the objects must form a tree.',
                $prefix === '' ? 'entity' : rtrim($prefix, '.'),
            ));
        }
        $path[] = $object;

        $data = [];
        foreach ($schema->getFields() as $field) {
            $value = $field->reader !== null
                ? ($field->reader)($object)
                : $this->accessor->read($object, $field->name);

            $data[$field->name] = $this->toPlain($field, $value, $prefix . $field->name, $path);
        }

        return $data;
    }

    /**
     * @param list<object> $objects
     */
    private function toPlain(Field $field, mixed $value, string $path, array $objects): mixed
    {
        if ($value === null) {
            return $field->nullable
                ? null
                : throw new InvalidValueException(sprintf('Field "%s" must not be null.', $path));
        }

        return match ($field->type) {
            FieldType::Object => $this->extractRelated($this->relationSchema($field), $value, $path, $objects),
            FieldType::Collection => $this->extractCollection($field, $value, $path, $objects),
            default => self::plain($field->type, $value, sprintf('Field "%s"', $path)),
        };
    }

    /**
     * @param Schema<covariant object> $schema
     * @param list<object> $objects
     *
     * @return array<string, mixed>
     */
    private function extractRelated(Schema $schema, mixed $value, string $path, array $objects): array
    {
        return $this->extractObject($schema, $this->assertInstance($schema, $value, $path), $path . '.', $objects);
    }

    /**
     * @param list<object> $objects
     *
     * @return list<array<string, mixed>>
     */
    private function extractCollection(Field $field, mixed $value, string $path, array $objects): array
    {
        if (!is_iterable($value)) {
            throw self::mismatch(sprintf('Field "%s"', $path), 'iterable', $value);
        }

        $schema = $this->relationSchema($field);

        $items = [];
        foreach ($value as $item) {
            $items[] = $this->extractRelated($schema, $item, $path . '.' . count($items), $objects);
        }

        return $items;
    }

    // ---- hydrate ----------------------------------------------------------------------------------

    /**
     * @param Schema<covariant object> $schema
     * @param array<array-key, mixed> $data
     */
    private function hydrateObject(Schema $schema, object $target, array $data, string $prefix): void
    {
        foreach ($schema->getFields() as $field) {
            if (!array_key_exists($field->name, $data)) {
                continue; // the field was added after this version was stored: keep the current value
            }

            $value = $this->fromPlain($field, $data[$field->name], $prefix . $field->name);

            if ($field->writer !== null) {
                ($field->writer)($target, $value);
            } else {
                $this->accessor->write($target, $field->name, $value);
            }
        }
    }

    private function fromPlain(Field $field, mixed $plain, string $path): mixed
    {
        if ($plain === null) {
            return $field->nullable
                ? null
                : throw new InvalidValueException(sprintf('Stored value of field "%s" is null, but the field is not nullable.', $path));
        }

        return match ($field->type) {
            FieldType::Object => $this->hydrateRelated($this->relationSchema($field), $plain, $path),
            FieldType::Collection => $this->hydrateCollection($field, $plain, $path),
            default => self::plain($field->type, $plain, sprintf('Stored value of field "%s"', $path)),
        };
    }

    /**
     * @param Schema<covariant object> $schema
     */
    private function hydrateRelated(Schema $schema, mixed $plain, string $path): object
    {
        if (!is_array($plain)) {
            throw self::mismatch(sprintf('Stored value of field "%s"', $path), 'array', $plain);
        }

        $object = $schema->create();
        $this->hydrateObject($schema, $object, $plain, $path . '.');

        return $object;
    }

    /**
     * @return list<object>
     */
    private function hydrateCollection(Field $field, mixed $plain, string $path): array
    {
        if (!is_array($plain) || !array_is_list($plain)) {
            throw self::mismatch(sprintf('Stored value of field "%s"', $path), 'list', $plain);
        }

        $schema = $this->relationSchema($field);

        $items = [];
        foreach ($plain as $index => $item) {
            $items[] = $this->hydrateRelated($schema, $item, $path . '.' . $index);
        }

        return $items;
    }

    // ---- helpers ----------------------------------------------------------------------------------

    /**
     * @return RelationSchema<covariant object>
     */
    private function relationSchema(Field $field): RelationSchema
    {
        $class = $field->schemaClass ?? throw new InvalidSchemaException(sprintf('Relation field "%s" has no schema.', $field->name));

        return $this->schemas->get($class);
    }

    /**
     * A value of a plain field, checked by the same rules on the way in and out. An int is accepted as float.
     *
     * @param string $subject Who holds the value, for the message: 'Field "title"', 'Stored value of field "title"'.
     */
    private static function plain(FieldType $type, mixed $value, string $subject): mixed
    {
        $matches = match ($type) {
            FieldType::String => is_string($value),
            FieldType::Int => is_int($value),
            FieldType::Float => is_float($value) || is_int($value),
            FieldType::Bool => is_bool($value),
            FieldType::Array => is_array($value),
            FieldType::Object, FieldType::Collection => false, // relations never get here
        };

        if (!$matches) {
            throw self::mismatch($subject, strtolower($type->name), $value);
        }

        return $type === FieldType::Float && is_int($value) ? (float) $value : $value;
    }

    private static function mismatch(string $subject, string $expected, mixed $value): InvalidValueException
    {
        return new InvalidValueException(sprintf('%s must be %s, %s given.', $subject, $expected, get_debug_type($value)));
    }
}
