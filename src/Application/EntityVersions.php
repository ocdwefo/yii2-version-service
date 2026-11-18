<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Application;

use NoDiscard;
use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Exception\UnknownAuthorException;
use Ocdwefo\VersionService\Domain\Exception\VersionConflictException;
use Ocdwefo\VersionService\Domain\Exception\VersionNotFoundException;
use Ocdwefo\VersionService\Domain\Model\AuthorId;
use Ocdwefo\VersionService\Domain\Model\Comment;
use Ocdwefo\VersionService\Domain\Model\EntityId;
use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\EntityType;
use Ocdwefo\VersionService\Domain\Model\Version;
use Ocdwefo\VersionService\Domain\Model\VersionHeader;
use Ocdwefo\VersionService\Domain\Model\VersionId;
use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Domain\Schema\SnapshotMapper;
use Ocdwefo\VersionService\Domain\Service\VersionFactory;

/**
 * Versions of the entities of one schema. Get it once from {@see VersionService::of()} and reuse it.
 *
 * ```php
 * $articleVersions = $versionService->of(new ArticleSchema());
 *
 * $articleVersions->record($article, 'Fixed the title');
 * $page = $articleVersions->history($article->id);
 * $old = $articleVersions->checkout($article, $versionId);   // show it in the form, save it to restore
 * ```
 *
 * The package opens no transactions: wrap saving the entity and record() in a transaction of the project.
 *
 * @template T of object
 */
final readonly class EntityVersions
{
    /** How many times record() tries when concurrent writes take its version number. */
    private const int MAX_ATTEMPTS = 3;

    private EntityType $type;

    /**
     * @param RootSchema<T> $schema
     */
    public function __construct(
        private RootSchema $schema,
        private VersionRepositoryInterface $repository,
        private SnapshotMapper $mapper,
        private VersionFactory $factory,
        private AuthorResolverInterface $authors,
    ) {
        $this->type = EntityType::fromEnum($schema->entityType());
    }

    /**
     * Stores the current state of the entity as a new version. Always creates one:
     * whether anything changed is for the project to decide before calling.
     *
     * @param T $entity
     * @param int|null $authorId Defaults to {@see AuthorResolverInterface::current()}: the logged in user.
     *     Code running without a user (console, cron, import) passes it.
     *
     * @throws InvalidValueException When the comment or a field value is invalid.
     * @throws InvalidSchemaException
     * @throws VersionConflictException When concurrent writes took the number on every attempt.
     * @throws UnknownAuthorException When no author is given and nobody is logged in.
     */
    public function record(object $entity, string $comment, ?int $authorId = null): VersionView
    {
        $validComment = Comment::fromString($comment);
        $author = $this->resolveAuthor($authorId);
        $snapshot = $this->mapper->extract($this->schema, $entity);
        $reference = $this->reference($this->mapper->entityId($this->schema, $entity));

        for ($attempt = 1; ; $attempt++) {
            $version = $this->factory->create($reference, $snapshot, $validComment, $author);

            try {
                $this->repository->add($version);
            } catch (VersionConflictException $conflict) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $conflict;
                }

                continue;
            }

            return $this->view($version);
        }
    }

    /**
     * Versions of the entity, newest first: number, author, date and comment only.
     * Snapshots are not loaded; take the values of one version with get() or checkout().
     */
    #[NoDiscard]
    public function history(int $entityId, int $limit = 20, int $offset = 0): VersionPage
    {
        if ($limit < 1) {
            throw new InvalidValueException(sprintf('Limit must be positive, %d given.', $limit));
        }
        if ($offset < 0) {
            throw new InvalidValueException(sprintf('Offset must not be negative, %d given.', $offset));
        }

        $reference = $this->reference($entityId);

        return new VersionPage(
            array_map(
                static fn (VersionHeader $header): VersionSummary => VersionSummary::fromHeader($header),
                $this->repository->history($reference, $limit, $offset),
            ),
            $this->repository->count($reference),
        );
    }

    /**
     * @throws VersionNotFoundException Also when the version belongs to another entity.
     */
    #[NoDiscard]
    public function get(int $entityId, string $versionId): VersionView
    {
        return $this->view($this->find($entityId, $versionId));
    }

    /**
     * Copy of the entity with the values of the version; the entity itself stays untouched.
     * Show the copy in the form to view the old version, save it to restore the version.
     *
     * @param T $entity
     *
     * @return T
     *
     * @throws VersionNotFoundException Also when the version belongs to another entity.
     */
    #[NoDiscard]
    public function checkout(object $entity, string $versionId): object
    {
        $version = $this->find($this->mapper->entityId($this->schema, $entity), $versionId);

        return $this->mapper->hydrate($this->schema, $entity, $version->snapshot);
    }

    /**
     * Removes the whole history of the entity, e.g. after the entity itself is deleted.
     *
     * @return int Number of removed versions.
     */
    public function removeAll(int $entityId): int
    {
        return $this->repository->removeAll($this->reference($entityId));
    }

    private function find(int $entityId, string $versionId): Version
    {
        $reference = $this->reference($entityId);
        $id = VersionId::fromString($versionId);

        $version = $this->repository->find($id);
        if ($version === null || !$version->belongsTo($reference)) {
            throw VersionNotFoundException::of($id, $reference);
        }

        return $version;
    }

    private function resolveAuthor(?int $authorId): AuthorId
    {
        return AuthorId::fromInt($authorId ?? $this->authors->current());
    }

    private function reference(int $entityId): EntityReference
    {
        return new EntityReference($this->type, EntityId::fromInt($entityId));
    }

    private function view(Version $version): VersionView
    {
        return VersionView::fromVersion($version);
    }
}
