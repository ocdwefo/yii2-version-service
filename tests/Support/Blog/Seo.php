<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

/**
 * Encapsulated: reached through getters and setters only.
 */
final class Seo
{
    private string $metaTitle = '';

    private ?string $metaDescription = null;

    public static function of(string $metaTitle, ?string $metaDescription): self
    {
        $seo = new self();
        $seo->setMetaTitle($metaTitle);
        $seo->setMetaDescription($metaDescription);

        return $seo;
    }

    public function getMetaTitle(): string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }
}
