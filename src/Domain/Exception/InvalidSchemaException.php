<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Exception;

/**
 * A schema is configured wrongly: a programming error in the project, not bad data.
 */
final class InvalidSchemaException extends VersioningException
{
}
