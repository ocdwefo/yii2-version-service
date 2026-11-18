<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;

/**
 * Strategy reaching the value of a field in an object: property, getter/setter, ActiveRecord attribute...
 *
 * One for the whole project, bound in the DI container: ActiveRecordAccessor with ActiveRecord, {@see ObjectAccessor} without.
 * It decides by the object itself how to reach a field, so one accessor serves every class of a schema tree.
 */
interface FieldAccessorInterface
{
    /**
     * @throws InvalidSchemaException When the object has no readable field of the name.
     */
    public function read(object $object, string $name): mixed;

    /**
     * @throws InvalidSchemaException When the object has no writable field of the name.
     */
    public function write(object $object, string $name, mixed $value): void;
}
