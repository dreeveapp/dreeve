<?php

namespace App\Tests\Controller;

use App\Controller\SecuredImageRequestHandler;
use App\Infrastructure\Config\DemoMode;
use App\Infrastructure\Security\TrustedVisitor;
use App\Tests\ContainerTestCase;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Core\User\UserInterface;

class SecuredImageRequestHandlerTest extends ContainerTestCase
{
    // 1x1 transparent PNG.
    private const string PNG_BYTES = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\x0bIDATx\xda\x63\xf8\xcf\xc0\xf0\x1f\x00\x05\x05\x02\x00\x4a\xd0\x9d\x6b\x00\x00\x00\x00IEND\xaeB`\x82";

    private FilesystemOperator $fileStorage;

    public function testItServesTheRealImageToALoggedInUserInDemoMode(): void
    {
        $this->fileStorage->write('activities/photo.png', self::PNG_BYTES);

        $handler = $this->handler(demoModeIsEnabled: true, loggedIn: true);
        $response = $handler->handle('activities/photo.png');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('image/png', $response->headers->get('Content-Type'));
        $this->assertEquals(self::PNG_BYTES, $this->captureStreamedContent($response));
    }

    public function testItServesAnAnonymizedImageToAnAnonymousUserInDemoMode(): void
    {
        $this->fileStorage->write('activities/photo.png', self::PNG_BYTES, []);

        $handler = $this->handler(demoModeIsEnabled: true, loggedIn: false);
        $response = $handler->handle('activities/photo.png');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringStartsWith('https://picsum.photos/seed/photo/', $response->getTargetUrl());
    }

    public function testItReturnsNotFoundWhenTheFileDoesNotExist(): void
    {
        $handler = $this->handler(demoModeIsEnabled: true, loggedIn: true);
        $response = $handler->handle('activities/missing.png');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testItServesTheRealImageToEveryoneWhenDemoModeIsDisabled(): void
    {
        $this->fileStorage->write('activities/photo.png', self::PNG_BYTES, []);

        $handler = $this->handler(demoModeIsEnabled: false, loggedIn: false);
        $response = $handler->handle('activities/photo.png');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(self::PNG_BYTES, $this->captureStreamedContent($response));
    }

    #[DataProvider('provideOrientationSeeds')]
    public function testItPicksTheAnonymizedImageOrientationBasedOnTheSeed(string $fileName, string $expectedUrl): void
    {
        $this->fileStorage->write('activities/'.$fileName, self::PNG_BYTES, []);

        $handler = $this->handler(demoModeIsEnabled: true, loggedIn: false);
        $response = $handler->handle('activities/'.$fileName);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($expectedUrl, $response->getTargetUrl());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideOrientationSeeds(): iterable
    {
        yield 'landscape' => ['landscape-photo.png', 'https://picsum.photos/seed/landscape-photo/1200/800'];
        yield 'portrait' => ['portrait-photo.png', 'https://picsum.photos/seed/portrait-photo/800/1200'];
    }

    private function handler(bool $demoModeIsEnabled, bool $loggedIn): SecuredImageRequestHandler
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($loggedIn ? $this->createStub(UserInterface::class) : null);

        return new SecuredImageRequestHandler(
            $this->fileStorage,
            new TrustedVisitor(
                DemoMode::fromString($demoModeIsEnabled ? '1' : '0'),
                $security,
            ),
        );
    }

    private function captureStreamedContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
