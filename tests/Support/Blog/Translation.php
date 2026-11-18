<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

final class Translation
{
    public string $locale = '';

    public string $title = '';

    /** @var list<Attachment> */
    public array $attachments = [];

    /**
     * @param list<Attachment> $attachments
     */
    public static function of(string $locale, string $title, array $attachments = []): self
    {
        $translation = new self();
        $translation->locale = $locale;
        $translation->title = $title;
        $translation->attachments = $attachments;

        return $translation;
    }
}
