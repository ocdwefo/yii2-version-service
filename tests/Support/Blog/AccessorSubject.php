<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

/**
 * Every way a field can (not) be reached.
 */
final class AccessorSubject
{
    public string $plain = 'plain';

    public readonly string $fixed;

    public int $uninitialized;

    public private(set) string $privatelySet = 'private set';

    private string $hidden = 'hidden';

    private string $metaTitle = 'meta';

    private bool $active = true;

    public function __construct()
    {
        $this->fixed = 'fixed';
    }

    public function getMetaTitle(): string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function hidden(): string
    {
        return $this->hidden;
    }
}
