<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use Ocdwefo\VersionService\Domain\Schema\Field;
use Ocdwefo\VersionService\Domain\Schema\RelationSchema;
use Override;

/**
 * Contains itself, like replies of a comment: the data decides how deep it goes.
 *
 * @extends RelationSchema<TreeNode>
 */
final class TreeNodeSchema extends RelationSchema
{
    #[Override]
    public function entityClass(): string
    {
        return TreeNode::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [Field::string('name'), Field::object('child', self::class)->nullable()];
    }
}
