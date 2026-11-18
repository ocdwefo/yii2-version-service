<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Domain\Model;

/**
 * Points to one versioned entity: its type together with its id.
 */
final readonly class EntityReference
{
    public function __construct(
        public EntityType $type,
        public EntityId $id,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->type->value === $other->type->value && $this->id->value === $other->id->value;
    }
}
