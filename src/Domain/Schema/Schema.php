<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;

/**
 * What every schema has: the class it describes and its versioned fields. Projects do not extend it directly,
 * they extend one of its two roles:
 *
 * - {@see RootSchema}: the versioned entity itself, which has a history;
 * - {@see RelationSchema}: a part of it, reached through Field::object() / Field::collection().
 *
 * Only fields the user edits belong to a schema; counters and values owned by other processes do not.
 *
 * @template T of object
 */
abstract class Schema
{
    /** @var list<Field>|null */
    private ?array $validatedFields = null;

    /**
     * @return class-string<T>
     */
    abstract public function entityClass(): string;

    /**
     * @return list<Field>
     */
    abstract protected function fields(): array;

    /**
     * New empty instance, filled field by field when a version is checked out.
     * Override when the constructor needs arguments.
     *
     * @return T
     */
    public function create(): object
    {
        $class = $this->entityClass();

        return new $class();
    }

    /**
     * The fields, checked once: at least one, unique names.
     *
     * @return list<Field>
     *
     * @throws InvalidSchemaException
     */
    final public function getFields(): array
    {
        return $this->validatedFields ??= $this->validate($this->fields());
    }

    /**
     * @param list<Field> $fields
     *
     * @return list<Field>
     */
    private function validate(array $fields): array
    {
        if ($fields === []) {
            throw new InvalidSchemaException(sprintf('%s must describe at least one field.', static::class));
        }

        $byName = [];
        foreach ($fields as $field) {
            if (isset($byName[$field->name])) {
                throw new InvalidSchemaException(sprintf('%s describes field "%s" twice.', static::class, $field->name));
            }

            $byName[$field->name] = $field;
        }

        return $fields;
    }
}
