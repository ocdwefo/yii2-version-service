<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
