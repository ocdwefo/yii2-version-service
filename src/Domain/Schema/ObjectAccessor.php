<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Override;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Accessor of any object: its public getters, setters and properties.
 *
 * The field name is used exactly as written, nothing is converted. For the field "publishedAt":
 * - reads with the public getter `getPublishedAt()` or `isPublishedAt()`, otherwise the public property `$publishedAt`;
 * - writes with the public setter `setPublishedAt()`, otherwise the public writable property `$publishedAt`.
 * The method name must match in case too. Anything else is described with readUsing() / writeUsing().
 */
final class ObjectAccessor implements FieldAccessorInterface
{
    #[Override]
    public function read(object $object, string $name): mixed
    {
        $suffix = ucfirst($name);
        foreach (['get', 'is'] as $prefix) {
            $getter = self::publicMethod($object, $prefix . $suffix);
            if ($getter !== null) {
                return $getter->invoke($object);
            }
        }

        $property = self::publicProperty($object, $name);
        if ($property === null) {
            throw new InvalidSchemaException(sprintf(
                'Cannot read field "%1$s" of %2$s: no public getter get%3$s()/is%3$s() or public property.',
                $name,
                $object::class,
                $suffix,
            ));
        }

        if (!$property->isInitialized($object)) {
            throw new InvalidSchemaException(sprintf('Property %s::$%s is not initialized.', $object::class, $name));
        }

        return $property->getValue($object);
    }

    #[Override]
    public function write(object $object, string $name, mixed $value): void
    {
        $method = 'set' . ucfirst($name);
        $setter = self::publicMethod($object, $method);
        if ($setter !== null) {
            $setter->invoke($object, $value);

            return;
        }

        $property = self::publicProperty($object, $name);
        if ($property === null || $property->isReadOnly() || $property->isPrivateSet() || $property->isProtectedSet()) {
            throw new InvalidSchemaException(sprintf(
                'Cannot write field "%s" of %s: no public setter %s() or public writable property.',
                $name,
                $object::class,
                $method,
            ));
        }

        $property->setValue($object, $value);
    }

    /**
     * PHP method names ignore case; the name must still match exactly, so "metatitle" does not reach getMetaTitle().
     */
    private static function publicMethod(object $object, string $name): ?ReflectionMethod
    {
        if (!method_exists($object, $name)) {
            return null;
        }

        $method = new ReflectionMethod($object, $name);

        return $method->name === $name && $method->isPublic() && !$method->isStatic() ? $method : null;
    }

    private static function publicProperty(object $object, string $name): ?ReflectionProperty
    {
        if (!property_exists($object, $name)) {
            return null;
        }

        $property = new ReflectionProperty($object, $name);

        return $property->isPublic() && !$property->isStatic() ? $property : null;
    }
}
