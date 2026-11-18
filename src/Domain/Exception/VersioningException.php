<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Exception;

use RuntimeException;

/**
 * Base of every exception thrown by the package: `catch (VersioningException)` catches them all.
 */
abstract class VersioningException extends RuntimeException
{
}
