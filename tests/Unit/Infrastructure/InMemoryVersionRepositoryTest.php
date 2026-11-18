<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Unit\Infrastructure;

use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Infrastructure\Persistence\InMemoryVersionRepository;
use Ocdwefo\VersionService\Testing\VersionRepositoryContractTestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryVersionRepository::class)]
final class InMemoryVersionRepositoryTest extends VersionRepositoryContractTestCase
{
    #[Override]
    protected function createRepository(): VersionRepositoryInterface
    {
        return new InMemoryVersionRepository();
    }
}
