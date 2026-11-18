<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RootSchema;
use Ocdwefo\VersionService\Tests\Support\TestEntityType;
use Override;

/**
 * Root of a tree of nodes: its child is described by TreeNodeSchema, which contains itself.
 *
 * @extends RootSchema<TreeNode>
 */
final class TreeRootSchema extends RootSchema
{
    #[Override]
    public function entityType(): TestEntityType
    {
        return TestEntityType::Page;
    }

    #[Override]
    public function entityClass(): string
    {
        return TreeNode::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [Field::string('name'), Field::object('child', TreeNodeSchema::class)->nullable()];
    }
}
