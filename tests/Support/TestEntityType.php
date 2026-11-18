<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support;

/**
 * Versioned entities of an imaginary project.
 */
enum TestEntityType: int
{
    case Article = 1;
    case Page = 2;
    case OutOfRange = 70000;
}
