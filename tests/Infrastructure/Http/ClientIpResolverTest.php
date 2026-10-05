<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\ClientIpResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ClientIpResolverTest extends TestCase
{
    /** @var string[] */
    private array $originalTrustedProxies;
    private int $originalTrustedHeaderSet;

    /**
     * @param string[]              $trustedProxies
     * @param array<string, string> $headers
     */
    #[DataProvider('provideRequests')]
    public function testResolve(array $trustedProxies, string $remoteAddress, array $headers, string $expectedIp): void
    {
        Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_FOR);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', $remoteAddress);
        $request->headers->add($headers);

        $this->assertEquals($expectedIp, new ClientIpResolver()->resolve($request));
    }

    public static function provideRequests(): iterable
    {
        yield 'untrusted proxy ignores forwarded for' => [[], '172.30.0.1', ['X-Forwarded-For' => '192.168.1.40'], '172.30.0.1'];
        yield 'trusted proxy uses forwarded for' => [['private_ranges'], '172.30.0.1', ['X-Forwarded-For' => '203.0.113.5'], '203.0.113.5'];
        yield 'trusted proxy without forwarded headers' => [['private_ranges'], '192.168.65.1', [], '192.168.65.1'];
        yield 'trusted proxy prefers cloudflare connecting ip' => [['private_ranges'], '172.30.0.1', ['X-Forwarded-For' => '203.0.113.5', 'CF-Connecting-IP' => '198.51.100.7'], '198.51.100.7'];
        yield 'spoofed cloudflare connecting ip' => [[], '203.0.113.5', ['CF-Connecting-IP' => '198.51.100.7'], '203.0.113.5'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTrustedProxies = Request::getTrustedProxies();
        $this->originalTrustedHeaderSet = Request::getTrustedHeaderSet();
    }

    #[\Override]
    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->originalTrustedProxies, $this->originalTrustedHeaderSet);
    }
}
