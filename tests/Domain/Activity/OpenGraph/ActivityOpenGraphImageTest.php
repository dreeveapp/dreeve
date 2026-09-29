<?php

namespace App\Tests\Domain\Activity\OpenGraph;

use App\Domain\Activity\OpenGraph\ActivityOpenGraphImage;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityOpenGraphImageTest extends ContainerTestCase
{
    private const string PROFILE_PICTURE_URL = 'https://example.com/me.png';
    private const array WHITE = ['red' => 255, 'green' => 255, 'blue' => 255, 'alpha' => 0];

    private SettingsRepository $settingsRepository;

    public function testItRendersAPngOfTheOpenGraphSize(): void
    {
        $httpClient = $this->createMock(Client::class);
        $httpClient->expects($this->never())->method('get');

        $openGraphImage = new ActivityOpenGraphImage(
            settingsRepository: $this->settingsRepository,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            client: $httpClient,
            kernelProjectDir: $this->getContainer()->get(KernelProjectDir::class),
        );
        $size = getimagesizefromstring($openGraphImage->render(ActivityBuilder::fromDefaults()->build()));

        $this->assertIsArray($size);
        $this->assertEquals([ActivityOpenGraphImage::WIDTH, ActivityOpenGraphImage::HEIGHT, 'image/png'], [$size[0], $size[1], $size['mime']]);
    }

    #[DataProvider('provideActivityNames')]
    public function testItRendersAnyActivityName(string $name): void
    {
        $httpClient = $this->createMock(Client::class);
        $httpClient->expects($this->never())->method('get');

        $openGraphImage = new ActivityOpenGraphImage(
            settingsRepository: $this->settingsRepository,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            client: $httpClient,
            kernelProjectDir: $this->getContainer()->get(KernelProjectDir::class),
        );

        $this->assertInstanceOf(\GdImage::class, imagecreatefromstring(
            $openGraphImage->render(ActivityBuilder::fromDefaults()->withName($name)->build())
        ));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideActivityNames(): iterable
    {
        yield 'a short name' => ['Zone 2'];
        yield 'a name that wraps' => ['Race: Community Racing Festival on Rolling Highlands'];
        yield 'a name that does not fit on two lines' => [str_repeat('Rolling Highlands ', 10)];
        yield 'a single word wider than the card' => [str_repeat('W', 60)];
        yield 'a name with emoji' => ['Morning Run 🏃‍♂️☀️'];
    }

    public function testItDrawsTheProfilePicture(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::GENERAL, [
            ...$this->settingsRepository->findGroup(SettingsGroup::GENERAL),
            'profilePictureUrl' => self::PROFILE_PICTURE_URL,
        ]);
        $httpClient = $this->createMock(Client::class);
        $httpClient
            ->expects($this->once())
            ->method('get')
            ->with(self::PROFILE_PICTURE_URL, ['timeout' => 3])
            ->willReturn(new Response(200, [], (string) file_get_contents(__DIR__.'/fixtures/profile-picture.png')));

        $openGraphImage = new ActivityOpenGraphImage(
            settingsRepository: $this->settingsRepository,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            client: $httpClient,
            kernelProjectDir: $this->getContainer()->get(KernelProjectDir::class),
        );

        $this->assertEquals(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], $this->pixelInsideTheAvatar($openGraphImage->render(ActivityBuilder::fromDefaults()->build())));
    }

    public function testItSkipsAProfilePictureThatCannotBeFetched(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::GENERAL, [
            ...$this->settingsRepository->findGroup(SettingsGroup::GENERAL),
            'profilePictureUrl' => self::PROFILE_PICTURE_URL,
        ]);
        $httpClient = $this->createMock(Client::class);
        $httpClient
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new ConnectException('Timed out', new Request('GET', self::PROFILE_PICTURE_URL)));

        $openGraphImage = new ActivityOpenGraphImage(
            settingsRepository: $this->settingsRepository,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            client: $httpClient,
            kernelProjectDir: $this->getContainer()->get(KernelProjectDir::class),
        );

        $this->assertEquals(self::WHITE, $this->pixelInsideTheAvatar($openGraphImage->render(ActivityBuilder::fromDefaults()->build())));
    }

    public function testItSkipsAProfilePictureThatIsNotAnImage(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::GENERAL, [
            ...$this->settingsRepository->findGroup(SettingsGroup::GENERAL),
            'profilePictureUrl' => self::PROFILE_PICTURE_URL,
        ]);
        $httpClient = $this->createMock(Client::class);
        $httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn(new Response(200, [], '<html>Not found</html>'));

        $openGraphImage = new ActivityOpenGraphImage(
            settingsRepository: $this->settingsRepository,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            client: $httpClient,
            kernelProjectDir: $this->getContainer()->get(KernelProjectDir::class),
        );

        $this->assertEquals(self::WHITE, $this->pixelInsideTheAvatar($openGraphImage->render(ActivityBuilder::fromDefaults()->build())));
    }

    public function testItDoesNotFetchAProfilePictureWhenNoneIsConfigured(): void
    {
        $httpClient = $this->createMock(Client::class);
        $httpClient->expects($this->never())->method('get');

        $openGraphImage = new ActivityOpenGraphImage(
            settingsRepository: $this->settingsRepository,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            client: $httpClient,
            kernelProjectDir: $this->getContainer()->get(KernelProjectDir::class),
        );

        $this->assertEquals(self::WHITE, $this->pixelInsideTheAvatar($openGraphImage->render(ActivityBuilder::fromDefaults()->build())));
    }

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    private function pixelInsideTheAvatar(string $png): array
    {
        $image = imagecreatefromstring($png);
        $this->assertInstanceOf(\GdImage::class, $image);

        return imagecolorsforindex($image, imagecolorat($image, 128, 90));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class);
    }
}
