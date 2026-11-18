<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

/**
 * Type of a schema field: how its value is turned into a plain snapshot value and back.
 */
enum FieldType
{
    case String;
    case Int;
    case Float;
    case Bool;
    /** Array of plain values, stored as is. */
    case Array;
    /** One related object (one-to-one), described by its own schema. */
    case Object;
    /** List of related objects (one-to-many), described by their own schema. */
    case Collection;
}
