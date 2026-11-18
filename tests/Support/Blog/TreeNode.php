<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

final class TreeNode
{
    public string $name = '';

    public ?TreeNode $child = null;
}
