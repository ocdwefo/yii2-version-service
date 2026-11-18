<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Schema;

use BackedEnum;

/**
 * Schema of a versioned entity: the root of a snapshot, the only kind of schema that has a history.
 * Its parts are described by {@see RelationSchema}.
 *
 * ```php
 * // annotated with: @extends RootSchema<Article>
 * final class ArticleSchema extends RootSchema
 * {
 *     public function entityType(): VersionType { return VersionType::Article; }
 *     public function entityClass(): string { return Article::class; }
 *
 *     protected function fields(): array
 *     {
 *         return [
 *             Field::string('title'),
 *             Field::int('status'),
 *             Field::collection('translations', TranslationSchema::class),
 *         ];
 *     }
 * }
 * ```
 *
 * @template T of object
 *
 * @extends Schema<T>
 */
abstract class RootSchema extends Schema
{
    /**
     * Case of the project enum listing the versioned entities. Must be int backed.
     */
    abstract public function entityType(): BackedEnum;

    /**
     * How many of the newest versions of this entity survive pruning, null for the limit from the config.
     * Must be at least 1.
     */
    public function keepVersions(): ?int
    {
        return null;
    }

    /**
     * Name of the field holding the integer primary key of the entity. Override when the key is named differently.
     */
    public function idField(): string
    {
        return 'id';
    }
}
