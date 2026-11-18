<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Infrastructure\Yii2\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Ocdwefo\VersionService\Domain\Schema\FieldAccessorInterface;
use Ocdwefo\VersionService\Domain\Schema\ObjectAccessor;
use Override;
use yii\db\ActiveRecordInterface;
use yii\db\BaseActiveRecord;

/**
 * Accessor of a Yii project: ActiveRecord models and any other objects.
 *
 * ```php
 * FieldAccessorInterface::class => ActiveRecordAccessor::class,
 * ```
 *
 * - Attribute (table column): getAttribute() / setAttribute().
 * - Relation (getTranslations() returning a query): read as the related records, written with
 *   populateRelation() — in memory only, nothing is saved.
 * - Anything else: public getter/setter or property with the exact name, like {@see ObjectAccessor}.
 */
final class ActiveRecordAccessor implements FieldAccessorInterface
{
    private readonly ObjectAccessor $fallback;

    public function __construct()
    {
        $this->fallback = new ObjectAccessor();
    }

    #[Override]
    public function read(object $object, string $name): mixed
    {
        if ($object instanceof BaseActiveRecord) {
            if ($object->hasAttribute($name)) {
                return $object->getAttribute($name);
            }

            if ($object->getRelation($name, false) !== null) {
                return $object->__get($name); // loads the relation unless it is populated already
            }
        }

        return $this->fallback->read($object, $name);
    }

    #[Override]
    public function write(object $object, string $name, mixed $value): void
    {
        if ($object instanceof BaseActiveRecord) {
            if ($object->hasAttribute($name)) {
                $object->setAttribute($name, $value);

                return;
            }

            if ($object->getRelation($name, false) !== null) {
                $object->populateRelation($name, self::relatedRecords($object, $name, $value));

                return;
            }
        }

        $this->fallback->write($object, $name, $value);
    }

    /**
     * @return ActiveRecordInterface|array<array-key, ActiveRecordInterface>|null
     */
    private static function relatedRecords(BaseActiveRecord $owner, string $name, mixed $value): ActiveRecordInterface|array|null
    {
        if ($value === null || $value instanceof ActiveRecordInterface) {
            return $value;
        }

        if (is_array($value)) {
            $records = [];
            foreach ($value as $key => $record) {
                if (!$record instanceof ActiveRecordInterface) {
                    throw self::notRecords($owner, $name, $record);
                }
                $records[$key] = $record;
            }

            return $records;
        }

        throw self::notRecords($owner, $name, $value);
    }

    private static function notRecords(BaseActiveRecord $owner, string $name, mixed $value): InvalidSchemaException
    {
        return new InvalidSchemaException(sprintf(
            'Relation %s::$%s can hold ActiveRecord models only, %s given: describe the related ActiveRecord class in the schema of the relation.',
            $owner::class,
            $name,
            get_debug_type($value),
        ));
    }
}
