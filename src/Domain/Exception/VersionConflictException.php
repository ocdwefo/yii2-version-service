<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Exception;

use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\VersionNumber;
use Throwable;

/**
 * Another process stored a version of the same entity with the same number first.
 * Recording the version again takes the next free number.
 */
final class VersionConflictException extends VersioningException
{
    public static function numberTaken(EntityReference $entity, VersionNumber $number, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Version %d of entity %d#%d is already taken by a concurrent write.',
                $number->value,
                $entity->type->value,
                $entity->id->value,
            ),
            0,
            $previous,
        );
    }
}
