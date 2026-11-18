<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Blog;

final class Attachment
{
    public string $name = '';

    public int $size = 0;

    public static function of(string $name, int $size): self
    {
        $attachment = new self();
        $attachment->name = $name;
        $attachment->size = $size;

        return $attachment;
    }
}
