<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\ApiRequestHandler;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\CompressedString;
use App\Tests\ContainerTestCase;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;

class ApiRequestHandlerTest extends ContainerTestCase
{
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

    public function testHandleWhenFileCouldNotBeRead(): void
    {
        $this->apiRequestHandler = new ApiRequestHandler(
            $fileSystem = $this->createMock(FilesystemOperator::class),
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

        $this->apiRequestHandler = new ApiRequestHandler(
            $this->getContainer()->get('build_api.storage'),
        );
    }
}
