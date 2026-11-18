<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Domain\Schema;

use Ocdwefo\VersionService\Domain\Exception\InvalidSchemaException;
use Ocdwefo\VersionService\Domain\Schema\ObjectAccessor;
use Ocdwefo\VersionService\Tests\Support\Blog\AccessorSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObjectAccessor::class)]
final class ObjectAccessorTest extends TestCase
{
    private ObjectAccessor $accessor;

    private AccessorSubject $subject;

    #[\Override]
    protected function setUp(): void
    {
        $this->accessor = new ObjectAccessor();
        $this->subject = new AccessorSubject();
    }

    public function testReadsPublicProperty(): void
    {
        self::assertSame('plain', $this->accessor->read($this->subject, 'plain'));
        self::assertSame('fixed', $this->accessor->read($this->subject, 'fixed'));
    }

    public function testReadsGetter(): void
    {
        self::assertSame('meta', $this->accessor->read($this->subject, 'metaTitle'));
    }

    /**
     * Names are not converted: snake case, another case or the getter's own name reach nothing.
     */
    #[TestWith(['meta_title'])]
    #[TestWith(['metatitle'])]
    #[TestWith(['MetaTitle_'])]
    public function testReadsNothingButTheExactName(string $name): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->accessor->read($this->subject, $name);
    }

    public function testWritesNothingButTheExactName(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->accessor->write($this->subject, 'meta_title', 'new meta');
    }

    public function testReadsBooleanIsser(): void
    {
        self::assertTrue($this->accessor->read($this->subject, 'active'));
    }

    public function testWritesPublicProperty(): void
    {
        $this->accessor->write($this->subject, 'plain', 'changed');

        self::assertSame('changed', $this->subject->plain);
    }

    public function testWritesThroughSetter(): void
    {
        $this->accessor->write($this->subject, 'metaTitle', 'new meta');

        self::assertSame('new meta', $this->subject->getMetaTitle());
    }

    #[TestWith(['hidden'])]
    #[TestWith(['missing'])]
    public function testCannotReadPrivateOrMissingField(string $name): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->accessor->read($this->subject, $name);
    }

    public function testReadErrorNamesTheGettersItLookedFor(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('getPublishedAt()/isPublishedAt()');

        $this->accessor->read($this->subject, 'publishedAt');
    }

    public function testCannotReadUninitializedProperty(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('not initialized');

        $this->accessor->read($this->subject, 'uninitialized');
    }

    #[TestWith(['fixed'])]
    #[TestWith(['privatelySet'])]
    #[TestWith(['hidden'])]
    #[TestWith(['active'])]
    #[TestWith(['missing'])]
    public function testCannotWriteNotWritableField(string $name): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->accessor->write($this->subject, $name, 'value');
    }
}
