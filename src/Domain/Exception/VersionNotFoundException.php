<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Exception;

use Ocdwefo\VersionService\Domain\Model\EntityReference;
use Ocdwefo\VersionService\Domain\Model\VersionId;

/**
 * No such version, or it belongs to another entity: both look the same to the caller,
 * so a version id cannot be used to peek into a foreign history.
 */
final class VersionNotFoundException extends VersioningException
{
    public static function of(VersionId $id, EntityReference $entity): self
    {
        return new self(sprintf('Version %s of entity %d#%d not found.', $id->value, $entity->type->value, $entity->id->value));
    }
}
