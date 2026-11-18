<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Application;

/**
 * One page of the history of an entity, newest version first. Items carry no stored values.
 */
final readonly class VersionPage
{
    /**
     * @param list<VersionSummary> $items
     * @param int $total Number of versions of the entity on all pages.
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
