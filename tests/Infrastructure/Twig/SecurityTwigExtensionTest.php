<?php

namespace App\Tests\Infrastructure\Twig;

use App\Infrastructure\Security\AuthenticatedVisitor;
use App\Infrastructure\Twig\SecurityTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;

class SecurityTwigExtensionTest extends TestCase
{
    public function testItShouldReportAnAuthenticatedVisitor(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(new InMemoryUser('admin', null, ['ROLE_ADMIN']));

        self::assertTrue(new SecurityTwigExtension(new AuthenticatedVisitor($security))->isAuthenticated());
    }

    public function testItShouldReportAnAnonymousVisitor(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        self::assertFalse(new SecurityTwigExtension(new AuthenticatedVisitor($security))->isAuthenticated());
    }
}
