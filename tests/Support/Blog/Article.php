<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

use DateTimeImmutable;

final class Article
{
    public int $id = 0;

    public string $title = '';

    public ArticleStatus $status = ArticleStatus::Draft;

    public ?DateTimeImmutable $publishedAt = null;

    public float $price = 0.0;

    public bool $featured = false;

    /** @var list<string> */
    public array $tags = [];

    public ?Seo $seo = null;

    /** @var list<Translation> */
    public array $translations = [];

    /** Not versioned: changes outside the edit form. */
    public int $views = 0;
}
