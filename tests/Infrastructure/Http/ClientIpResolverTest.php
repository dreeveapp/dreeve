<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\ClientIpResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ClientIpResolverTest extends TestCase
{
    /** @var string[] */
    private array $originalTrustedProxies;
    private int $originalTrustedHeaderSet;
    private ClientIpResolver $clientIpResolver;

    public function testItReturnsTheRemoteAddressWhenTheRequestDoesNotComeFromATrustedProxy(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '172.30.0.1');
        $request->headers->set('X-Forwarded-For', '192.168.1.40');

        $this->assertEquals('172.30.0.1', $this->clientIpResolver->resolve($request));
    }

    public function testItReturnsTheForwardedForAddressWhenTheRequestComesFromATrustedProxy(): void
    {
        Request::setTrustedProxies(['private_ranges'], Request::HEADER_X_FORWARDED_FOR);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '172.30.0.1');
        $request->headers->set('X-Forwarded-For', '203.0.113.5');

        $this->assertEquals('203.0.113.5', $this->clientIpResolver->resolve($request));
    }

    public function testItFallsBackToTheRemoteAddressWhenATrustedProxySendsNoForwardedHeaders(): void
    {
        Request::setTrustedProxies(['private_ranges'], Request::HEADER_X_FORWARDED_FOR);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '192.168.65.1');

        $this->assertEquals('192.168.65.1', $this->clientIpResolver->resolve($request));
    }

    public function testItPrefersTheCloudflareConnectingIpHeaderOverTheForwardedForAddress(): void
    {
        Request::setTrustedProxies(['private_ranges'], Request::HEADER_X_FORWARDED_FOR);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '172.30.0.1');
        $request->headers->set('X-Forwarded-For', '203.0.113.5');
        $request->headers->set('CF-Connecting-IP', '198.51.100.7');

        $this->assertEquals('198.51.100.7', $this->clientIpResolver->resolve($request));
    }

    public function testItIgnoresASpoofedCloudflareConnectingIpHeader(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '203.0.113.5');
        $request->headers->set('CF-Connecting-IP', '198.51.100.7');

        $this->assertEquals('203.0.113.5', $this->clientIpResolver->resolve($request));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTrustedProxies = Request::getTrustedProxies();
        $this->originalTrustedHeaderSet = Request::getTrustedHeaderSet();
        $this->clientIpResolver = new ClientIpResolver();
    }

    #[\Override]
    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->originalTrustedProxies, $this->originalTrustedHeaderSet);
    }
}
