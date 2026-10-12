<?php

namespace App\Tests\Infrastructure\Security;

use App\Domain\Settings\SecuritySettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Config\DemoMode;
use App\Infrastructure\Security\Visitor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;

class VisitorTest extends TestCase
{
    #[DataProvider('provideVisitors')]
    public function testItKnowsWhatTheVisitorMayDo(
        bool $loggedIn,
        bool $demoModeIsEnabled,
        bool $visitorsCanDownloadOriginalFiles,
        bool $expectedIsAuthenticated,
        bool $expectedIsTrusted,
        bool $expectedCanDownloadOriginalFiles,
    ): void {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($loggedIn ? new InMemoryUser('admin', null, ['ROLE_ADMIN']) : null);
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository->method('security')->willReturn(
            SecuritySettings::fromArray(['visitorsCanDownloadOriginalFiles' => $visitorsCanDownloadOriginalFiles])
        );

        $visitor = new Visitor($security, DemoMode::fromString($demoModeIsEnabled ? '1' : '0'), $settingsRepository);

        $this->assertSame($expectedIsAuthenticated, $visitor->isAuthenticated());
        $this->assertSame($expectedIsTrusted, $visitor->isTrusted());
        $this->assertSame($expectedCanDownloadOriginalFiles, $visitor->canDownloadOriginalFiles());
    }

    public static function provideVisitors(): \Generator
    {
        yield 'anonymous' => [false, false, false, false, true, false];
        yield 'anonymous, demo mode' => [false, true, false, false, false, false];
        yield 'anonymous, original files allowed' => [false, false, true, false, true, true];
        yield 'anonymous, demo mode, original files allowed' => [false, true, true, false, false, true];
        yield 'logged in' => [true, false, false, true, true, true];
        yield 'logged in, demo mode' => [true, true, false, true, true, true];
    }
}
