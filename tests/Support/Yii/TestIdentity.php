<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Support\Yii;

use Override;
use yii\web\IdentityInterface;

final class TestIdentity implements IdentityInterface
{
    public function __construct(private readonly int|string $id)
    {
    }

    /**
     * @param mixed $id
     */
    #[Override]
    public static function findIdentity($id): ?self
    {
        return null;
    }

    /**
     * @param mixed $token
     * @param mixed $type
     */
    #[Override]
    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        return null;
    }

    #[Override]
    public function getId(): int|string
    {
        return $this->id;
    }

    #[Override]
    public function getAuthKey(): ?string
    {
        return null;
    }

    /**
     * @param mixed $authKey
     */
    #[Override]
    public function validateAuthKey($authKey): bool
    {
        return false;
    }
}
