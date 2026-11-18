<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Application;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\RetentionLimit;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Domain\Schema\SchemaRegistry;

/**
 * Removes the versions beyond the limit, for every entity of the listed schemas. Meant for a cron job.
 *
 * Every entity keeps its newest versions: as many as its schema's keepVersions() says, otherwise $keepVersions.
 * The latest version is never removed. A DI container passes the settings by name and supplies the rest:
 *
 * ```php
 * VersionPruner::class => ['__construct()' => ['keepVersions' => 20, 'schemas' => [ArticleSchema::class]]],
 * ```
 */
final readonly class VersionPruner
{
    private RetentionLimit $keepVersions;

    /**
     * @param int $keepVersions Newest versions kept per entity unless its schema says otherwise, at least 1.
     * @param list<class-string<RootSchema<covariant object>>> $schemas Schemas whose entities are pruned.
     *
     * @throws InvalidValueException When $keepVersions is less than one.
     */
    public function __construct(
        int $keepVersions,
        private array $schemas,
        private VersionRepositoryInterface $repository,
    ) {
        $this->keepVersions = RetentionLimit::fromInt($keepVersions);
    }

    /**
     * @return int Number of removed versions.
     *
     * @throws InvalidSchemaException When a listed class is not a version schema, or two share an entity type.
     * @throws InvalidValueException When a schema limit is less than one.
     */
    public function prune(): int
    {
        $removed = 0;
        foreach ($this->resolveSchemas() as $schema) {
            $removed += $this->repository->prune(EntityType::fromEnum($schema->entityType()), $this->limitFor($schema));
        }

        return $removed;
    }

    /**
     * @return list<RootSchema<covariant object>>
     */
    private function resolveSchemas(): array
    {
        $registry = new SchemaRegistry();
        $schemas = [];
        $owners = [];
        foreach ($this->schemas as $class) {
            $schema = $registry->get($class);
            if (!$schema instanceof RootSchema) {
                throw new InvalidSchemaException(sprintf('%s is listed for pruning but is not a %s.', $class, RootSchema::class));
            }

            $type = EntityType::fromEnum($schema->entityType())->value;
            if (isset($owners[$type])) {
                throw new InvalidSchemaException(sprintf('%s and %s use the same entity type %d.', $owners[$type], $class, $type));
            }
            $owners[$type] = $class;

            $schemas[] = $schema;
        }

        return $schemas;
    }

    /**
     * @param RootSchema<covariant object> $schema
     */
    private function limitFor(RootSchema $schema): RetentionLimit
    {
        $override = $schema->keepVersions();
        if ($override === null) {
            return $this->keepVersions;
        }

        if ($override < 1) {
            throw new InvalidValueException(sprintf(
                '%s::keepVersions() must be at least 1, the latest version is never removed; %d given.',
                $schema::class,
                $override,
            ));
        }

        return RetentionLimit::fromInt($override);
    }
}
