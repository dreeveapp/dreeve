<?php

namespace App\Tests\Controller\Api\Internal\Release;

use App\Application\AppVersion;
use App\Controller\Api\Internal\Release\LatestReleaseRequestHandler;
use App\Domain\Integration\GitHub\GitHub;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;

class LatestReleaseRequestHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $client = $this->createMock(Client::class);
        $client
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(fn (string $method, string $uri, array $options = []): Response => match ($uri) {
                'https://api.github.com/repos/dreeveapp/dreeve/releases/latest' => new Response(200, body: Json::encode(['name' => 'v999.0.0'])),
                'https://api.github.com/repos/dreeveapp/dreeve/actions/runs' => new Response(200, body: Json::encode(['workflow_runs' => [
                    ['path' => '.github/workflows/ci.yml'],
                    ['path' => '.github/workflows/docker-image.yml'],
                ]])),
            });

        $handler = new LatestReleaseRequestHandler(
            new GitHub($client),
            $this->getContainer()->get(CacheableRenderer::class),
        );

        $response = $handler->handle();
        $this->assertEquals(['latestVersion' => 'v999.0.0', 'updateAvailable' => true], Json::decode((string) $response->getContent()));
        $this->assertSame('MISS', $response->headers->get('X-Dreeve-Cache'));
        $this->assertSame('21600', $response->headers->get('X-Dreeve-Cache-TTL'));
        $this->assertSame('max-age=21600, private', $response->headers->get('Cache-Control'));

        $response = $handler->handle();
        $this->assertEquals(['latestVersion' => 'v999.0.0', 'updateAvailable' => true], Json::decode((string) $response->getContent()));
        $this->assertSame('HIT', $response->headers->get('X-Dreeve-Cache'));
    }

    public function testHandleWhenRunningTheLatestRelease(): void
    {
        $client = $this->createMock(Client::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.github.com/repos/dreeveapp/dreeve/releases/latest')
            ->willReturn(new Response(200, body: Json::encode(['name' => AppVersion::getSemanticVersion()])));

        $handler = new LatestReleaseRequestHandler(
            new GitHub($client),
            $this->getContainer()->get(CacheableRenderer::class),
        );

        $this->assertEquals(['latestVersion' => AppVersion::getSemanticVersion(), 'updateAvailable' => false], Json::decode((string) $handler->handle()->getContent()));
    }

    public function testHandleWhenTheDockerImageIsNotPublishedYet(): void
    {
        $client = $this->createMock(Client::class);
        $client
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(fn (string $method, string $uri, array $options = []): Response => match ($uri) {
                'https://api.github.com/repos/dreeveapp/dreeve/releases/latest' => new Response(200, body: Json::encode(['name' => 'v999.0.0'])),
                'https://api.github.com/repos/dreeveapp/dreeve/actions/runs' => new Response(200, body: Json::encode(['workflow_runs' => [
                    ['path' => '.github/workflows/ci.yml'],
                ]])),
            });

        $handler = new LatestReleaseRequestHandler(
            new GitHub($client),
            $this->getContainer()->get(CacheableRenderer::class),
        );

        $this->assertEquals(['latestVersion' => 'v999.0.0', 'updateAvailable' => false], Json::decode((string) $handler->handle()->getContent()));
    }

    public function testHandleWhenGitHubIsUnreachable(): void
    {
        $client = $this->createMock(Client::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new TransferException('Connection timed out'));

        $handler = new LatestReleaseRequestHandler(
            new GitHub($client),
            $this->getContainer()->get(CacheableRenderer::class),
        );

        $this->assertEquals(['latestVersion' => null, 'updateAvailable' => false], Json::decode((string) $handler->handle()->getContent()));
    }
}
