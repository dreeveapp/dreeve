<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache\Context;

use App\Infrastructure\Cache\CacheContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AuthenticatedCacheContext implements CacheContext
{
    public function __construct(
        private Security $security,
    ) {
    }

    public static function getKey(): string
    {
        return 'auth';
    }

    public function resolve(): string
    {
        return $this->security->getUser() instanceof UserInterface ? 'auth' : 'anon';
    }
}
