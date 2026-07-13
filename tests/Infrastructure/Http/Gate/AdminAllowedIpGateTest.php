<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Config\AdminAllowedIpAddresses;
use App\Infrastructure\Http\Gate\AdminAllowedIpGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminAllowedIpGateTest extends TestCase
{
    public function testItDeniesAdminAccessFromADisallowedIp(): void
    {
        $this->expectExceptionObject(new NotFoundHttpException('Not found'));

        $gate = new AdminAllowedIpGate(AdminAllowedIpAddresses::fromString('192.168.1.1'));
        $gate->handle($this->adminRequestFromIp('10.0.0.1'));
    }

    public function testItAllowsAdminAccessFromAnAllowedIp(): void
    {
        $gate = new AdminAllowedIpGate(AdminAllowedIpAddresses::fromString('192.168.1.1'));

        $this->assertFalse($gate->handle($this->adminRequestFromIp('192.168.1.1'))->hasBeenApplied());
    }

    public function testItPrefersTheCloudflareConnectingIpHeader(): void
    {
        $request = $this->adminRequestFromIp('192.168.1.1');
        $request->headers->set('CF-Connecting-IP', '10.0.0.1');

        $this->expectExceptionObject(new NotFoundHttpException('Not found'));

        $gate = new AdminAllowedIpGate(AdminAllowedIpAddresses::fromString('192.168.1.1'));
        $gate->handle($request);
    }

    public function testItAllowsEveryoneWhenNoAllowListIsConfigured(): void
    {
        $gate = new AdminAllowedIpGate(AdminAllowedIpAddresses::fromString(''));

        $this->assertFalse($gate->handle($this->adminRequestFromIp('10.0.0.1'))->hasBeenApplied());
    }

    #[DataProvider('provideNonAdminPaths')]
    public function testItIgnoresNonAdminPaths(string $path): void
    {
        $request = Request::create($path);
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        $gate = new AdminAllowedIpGate(AdminAllowedIpAddresses::fromString('192.168.1.1'));

        $this->assertFalse($gate->handle($request)->hasBeenApplied());
    }

    public static function provideNonAdminPaths(): iterable
    {
        yield 'home' => ['/'];
        yield 'a path that merely starts with admin' => ['/administration'];
    }

    private function adminRequestFromIp(string $ipAddress): Request
    {
        $request = Request::create('/admin/login');
        $request->server->set('REMOTE_ADDR', $ipAddress);

        return $request;
    }
}
