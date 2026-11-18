<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

/**
 * Schema of a part of a versioned entity: a related object reached through Field::object() or
 * Field::collection(). It has no history of its own, it is stored in the snapshot of its root.
 * It describes only its own fields, so nesting has no depth limit.
 *
 * ```php
 * // annotated with: @extends RelationSchema<Translation>
 * final class TranslationSchema extends RelationSchema
 * {
 *     public function entityClass(): string { return Translation::class; }
 *
 *     protected function fields(): array
 *     {
 *         return [Field::string('locale'), Field::string('title')];
 *     }
 * }
 * ```
 *
 * @template T of object
 *
 * @extends Schema<T>
 */
abstract class RelationSchema extends Schema
{
}
