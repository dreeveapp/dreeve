<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Config\AdminAllowedIpAddresses;
use App\Infrastructure\Http\ClientIpResolver;
use App\Infrastructure\Http\Gate\AdminAllowedIpGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminAllowedIpGateTest extends TestCase
{
    #[DataProvider('provideAllowedClients')]
    public function testItAllowsAdminAccess(string $allowList, string $ipAddress): void
    {
        $request = Request::create('/admin/login');
        $request->server->set('REMOTE_ADDR', $ipAddress);

        $gate = new AdminAllowedIpGate(AdminAllowedIpAddresses::fromString($allowList), new ClientIpResolver());

        $this->assertFalse($gate->handle($request)->hasBeenApplied());
    }

    public static function provideAllowedClients(): iterable
    {
        yield 'an allowed ip' => ['192.168.1.1', '192.168.1.1'];
        yield 'an ip inside an allowed range' => ['192.168.1.0/24', '192.168.1.40'];
        yield 'no allow list configured' => ['', '10.0.0.1'];
    }

    #[DataProvider('provideDeniedClients')]
    public function testItDeniesAdminAccess(string $allowList, string $ipAddress): void
    {
        $request = Request::create('/admin/login');
        $request->server->set('REMOTE_ADDR', $ipAddress);

        $this->expectExceptionObject(new NotFoundHttpException('Not found'));

        $gate = new AdminAllowedIpGate(AdminAllowedIpAddresses::fromString($allowList), new ClientIpResolver());
        $gate->handle($request);
    }

    public static function provideDeniedClients(): iterable
    {
        yield 'a disallowed ip' => ['192.168.1.1', '10.0.0.1'];
        yield 'an ip outside the allowed range' => ['192.168.1.0/24', '203.0.113.5'];
    }

    #[DataProvider('provideNonAdminPaths')]
    public function testItIgnoresNonAdminPaths(string $path): void
    {
        $request = Request::create($path);
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        $gate = new AdminAllowedIpGate(AdminAllowedIpAddresses::fromString('192.168.1.1'), new ClientIpResolver());

        $this->assertFalse($gate->handle($request)->hasBeenApplied());
    }

    public static function provideNonAdminPaths(): iterable
    {
        yield 'home' => ['/'];
        yield 'a path that merely starts with admin' => ['/administration'];
    }
}
