<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Exception;

/**
 * A value breaks an invariant of a domain object, so the object cannot be created.
 */
final class InvalidValueException extends VersioningException
{
}
