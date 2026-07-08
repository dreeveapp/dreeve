<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Twig\NavigationTwigExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class NavigationTwigExtensionTest extends TestCase
{
    #[DataProvider('provideRules')]
    public function testItShouldDetermineWhetherNavItemIsActive(
        string $currentPath,
        array $rules,
        bool $expected,
    ): void {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($currentPath));

        self::assertSame(
            $expected,
            new NavigationTwigExtension($requestStack, $this->createStub(SettingsRepository::class))->isActiveNavItem($rules)
        );
    }

    public static function provideRules(): iterable
    {
        yield 'exact match' => ['/admin', ['/admin' => true], true];
        yield 'exact rule does not match a sub-path' => ['/admin/upload', ['/admin' => true], false];
        yield 'exact rule does not match a different path' => ['/admin/gear', ['/admin' => true], false];

        yield 'sub-path rule matches the page itself' => ['/admin/dashboard', ['/admin/dashboard' => false], true];
        yield 'sub-path rule matches a child page' => ['/admin/dashboard/config', ['/admin/dashboard' => false], true];
        yield 'sub-path rule matches a deeper descendant' => ['/admin/dashboard/config/edit', ['/admin/dashboard' => false], true];
        yield 'sub-path rule does not match a sibling sharing a prefix' => ['/admin/dashboard-config', ['/admin/dashboard' => false], false];
        yield 'sub-path rule does not match an unrelated path' => ['/admin/gear', ['/admin/dashboard' => false], false];

        yield 'trailing slash in rule is normalised' => ['/admin/upload/history', ['/admin/upload/' => false], true];

        yield 'active when any of multiple rules matches the exact one' => ['/admin', ['/admin' => true, '/admin/upload' => false], true];
        yield 'active when any of multiple rules matches the sub-path one' => ['/admin/upload/history', ['/admin' => true, '/admin/upload' => false], true];
        yield 'inactive when no rule matches' => ['/admin/gear', ['/admin' => true, '/admin/upload' => false], false];

        yield 'empty rules are never active' => ['/admin', [], false];
    }

    public function testItIsNeverActiveWithoutACurrentRequest(): void
    {
        $extension = new NavigationTwigExtension(new RequestStack(), $this->createStub(SettingsRepository::class));

        self::assertFalse($extension->isActiveNavItem(['/admin' => true]));
    }
}
