<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Config\DemoMode;
use App\Infrastructure\Security\Visitor;
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

        self::assertTrue(new SecurityTwigExtension(new Visitor($security, DemoMode::fromString('0'), $this->createStub(SettingsRepository::class)))->isAuthenticated());
    }

    public function testItShouldReportAnAnonymousVisitor(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        self::assertFalse(new SecurityTwigExtension(new Visitor($security, DemoMode::fromString('0'), $this->createStub(SettingsRepository::class)))->isAuthenticated());
    }
}
