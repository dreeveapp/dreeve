<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\ApiRequestHandler;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Http\Page\PageRegistry;
use App\Infrastructure\Http\Page\PageRenderer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\CompressedString;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Spatie\Snapshots\MatchesSnapshots;

class ApiRequestHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private ApiRequestHandler $apiRequestHandler;

    public function testHandle(): void
    {
        /** @var \League\Flysystem\InMemory\InMemoryFilesystemAdapter $buildStorage */
        $buildStorage = $this->getContainer()->get('build_api.storage');
        $buildStorage->write('el-file.json', Json::encodeAndCompress(['lol']), []);

        $response = $this->apiRequestHandler->handle('el-file.json');
        $this->assertEquals(
            '["lol"]',
            $response->getContent()
        );
        $this->assertEquals(
            'application/json',
            $response->headers->get('Content-Type'),
        );
    }

    public function testHandleForGpxFile(): void
    {
        /** @var \League\Flysystem\InMemory\InMemoryFilesystemAdapter $buildStorage */
        $buildStorage = $this->getContainer()->get('build_api.storage');
        $buildStorage->write('el-file.gpx', CompressedString::fromUncompressed('<xml>'), []);

        $response = $this->apiRequestHandler->handle('el-file.gpx');
        $this->assertEquals(
            '<xml>',
            $response->getContent()
        );
        $this->assertEquals(
            'application/gpx+xml; charset=UTF-8',
            $response->headers->get('Content-Type'),
        );
    }

    public function testHandleWhenFileNotFound(): void
    {
        $this->assertEquals(
            404,
            $this->apiRequestHandler->handle('el-file.json')->getStatusCode()
        );
    }

    public function testHandleForRegisteredPage(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiRequestHandler->handle('photos');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'text/html; charset=UTF-8',
            $response->headers->get('Content-Type'),
        );
        // The page declares a cache context, so it must never be shared by a reverse proxy.
        $this->assertEquals(
            'no-store, private',
            $response->headers->get('Cache-Control'),
        );
        $this->assertStringContainsString('data-image', (string) $response->getContent());
        $this->assertFalse($response->headers->has('X-Cache-Hit'));
        $this->assertStringContainsString('photos.trust=', (string) $response->headers->get('X-Cache-Miss'));

        $secondResponse = $this->apiRequestHandler->handle('photos');
        $this->assertFalse($secondResponse->headers->has('X-Cache-Miss'));
        $this->assertEquals(
            $response->headers->get('X-Cache-Miss'),
            $secondResponse->headers->get('X-Cache-Hit'),
        );
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleForRegisteredPageWithoutCacheContexts(): void
    {
        $this->provideFullTestSet();

        $response = $this->apiRequestHandler->handle('challenges');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'text/html; charset=UTF-8',
            $response->headers->get('Content-Type'),
        );
        // The page declares no cache context, so its render can be shared by every visitor.
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringEndsWith('challenges', (string) $response->headers->get('X-Cache-Miss'));

        $secondResponse = $this->apiRequestHandler->handle('challenges');
        $this->assertFalse($secondResponse->headers->has('X-Cache-Miss'));
        $this->assertEquals(
            $response->headers->get('X-Cache-Miss'),
            $secondResponse->headers->get('X-Cache-Hit'),
        );
        $this->assertEquals($response->getContent(), $secondResponse->getContent());
    }

    public function testHandleWhenPathIsNeitherAPageNorAFile(): void
    {
        $this->assertEquals(
            404,
            $this->apiRequestHandler->handle('unknown')->getStatusCode()
        );
    }

    public function testHandleWhenFileCouldNotBeRead(): void
    {
        $this->apiRequestHandler = new ApiRequestHandler(
            $fileSystem = $this->createMock(FilesystemOperator::class),
            $this->getContainer()->get(PageRegistry::class),
            $this->getContainer()->get(PageRenderer::class),
        );

        $fileSystem
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(true);

        $fileSystem
            ->expects($this->once())
            ->method('read')
            ->willThrowException(new UnableToReadFile());

        $this->assertEquals(
            404,
            $this->apiRequestHandler->handle('el-file.json')->getStatusCode()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(RenderCache::class)->clear();
        $this->apiRequestHandler = new ApiRequestHandler(
            $this->getContainer()->get('build_api.storage'),
            $this->getContainer()->get(PageRegistry::class),
            $this->getContainer()->get(PageRenderer::class),
        );
    }
}
