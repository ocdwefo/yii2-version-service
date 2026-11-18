<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;

/**
 * Turns a schema class (of a relation field or of the pruning list) into its instance: created with `new`,
 * once per class. A schema is a description, so its constructor takes no arguments.
 */
final class SchemaRegistry
{
    /** @var array<string, Schema<covariant object>> */
    private array $schemas = [];

    /**
     * @template S of Schema<covariant object>
     *
     * @param class-string<S> $class
     *
     * @return S
     *
     * @throws InvalidSchemaException When the class is not a schema.
     */
    public function get(string $class): Schema
    {
        if (!isset($this->schemas[$class])) {
            if (!is_subclass_of($class, Schema::class)) {
                throw new InvalidSchemaException(sprintf('%s does not extend %s.', $class, Schema::class));
            }

            $this->schemas[$class] = new $class();
        }

        /** @var S $schema */
        $schema = $this->schemas[$class];

        return $schema;
    }
}
