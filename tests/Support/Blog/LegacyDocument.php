<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

/**
 * Entity with a string primary key, which the package does not support.
 */
final class LegacyDocument
{
    public string $id = 'doc-1';

    public string $title = '';
}
