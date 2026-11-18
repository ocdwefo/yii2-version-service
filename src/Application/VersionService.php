<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Application;

use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Domain\Schema\FieldAccessorInterface;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Domain\Schema\SnapshotMapper;
use Ocdwefo\VersionService\Domain\Service\VersionFactory;
use Psr\Clock\ClockInterface;

/**
 * Entry point of the package (facade). Holds no state between calls.
 *
 * ```php
 * $articleVersions = $versionService->of(new ArticleSchema());   // once, e.g. in a constructor
 * $articleVersions->record($article, $form->comment);
 * ```
 *
 * Pruning is a separate service: {@see VersionPruner}.
 */
final readonly class VersionService
{
    private SnapshotMapper $mapper;

    private VersionFactory $factory;

    /**
     * Every dependency is an interface, so a DI container builds the service without any settings.
     *
     * @param FieldAccessorInterface $accessor How fields of the project's objects are read and written, one for
     *     the whole project: {@see \Ocdwefo\VersionService\Infrastructure\Yii2\Schema\ActiveRecordAccessor} with
     *     ActiveRecord, {@see \Ocdwefo\VersionService\Domain\Schema\ObjectAccessor} without.
     */
    public function __construct(
        private VersionRepositoryInterface $repository,
        private AuthorResolverInterface $authors,
        ClockInterface $clock,
        FieldAccessorInterface $accessor,
    ) {
        $this->mapper = new SnapshotMapper($accessor);
        $this->factory = new VersionFactory($repository, $clock);
    }

    /**
     * Versions of the entities of the schema.
     *
     * @template T of object
     *
     * @param RootSchema<T> $schema
     *
     * @return EntityVersions<T>
     */
    public function of(RootSchema $schema): EntityVersions
    {
        return new EntityVersions($schema, $this->repository, $this->mapper, $this->factory, $this->authors);
    }
}
