<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Model;

use Ocdwefo\VersionService\Domain\Exception\InvalidValueException;
use Ocdwefo\VersionService\Domain\Model\Comment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Comment::class)]
final class CommentTest extends TestCase
{
    public function testTrimsSurroundingWhitespace(): void
    {
        self::assertSame('Виправив заголовок', Comment::fromString("  Виправив заголовок \n")->value);
    }

    public function testSingleCharacterIsEnough(): void
    {
        self::assertSame('x', Comment::fromString('x')->value);
    }

    public function testCountsCharactersNotBytes(): void
    {
        $comment = str_repeat('ї', Comment::MAX_LENGTH);

        self::assertSame($comment, Comment::fromString($comment)->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidComments(): iterable
    {
        yield 'empty' => [''];
        yield 'only whitespace' => ["  \t\n "];
        yield 'too long' => [str_repeat('ї', Comment::MAX_LENGTH + 1)];
        yield 'invalid UTF-8' => ["\xC3\x28"];
    }

    #[DataProvider('invalidComments')]
    public function testRejectsInvalidComment(string $value): void
    {
        $this->expectException(InvalidValueException::class);

        Comment::fromString($value);
    }
}
