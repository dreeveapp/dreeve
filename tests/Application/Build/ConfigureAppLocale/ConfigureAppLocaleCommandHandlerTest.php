<?php

namespace App\Tests\Application\Build\ConfigureAppLocale;

use App\Application\Build\BuildIndexHtml\BuildIndexHtml;
use App\Application\Build\ConfigureAppLocale\ConfigureAppLocale;
use App\Application\Build\ConfigureAppLocale\ConfigureAppLocaleCommandHandler;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Carbon\Carbon;
use League\Flysystem\FileAttributes;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Translation\LocaleSwitcher;

class ConfigureAppLocaleCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private string $snapshotName;
    private LocaleSwitcher $localeSwitcher;
    private SettingsRepository $settingsRepository;
    private CommandBus $commandBus;

    #[DataProvider(methodName: 'provideLocales')]
    public function testHandle(Locale $locale): void
    {
        $this->snapshotName = $locale->value;
        // Default locale should always be en_US
        $this->assertEquals(
            Locale::en_US->value,
            $this->localeSwitcher->getLocale()
        );
        $this->assertEquals(
            'en_US',
            Carbon::getLocale()
        );

        $this->configureLocaleHandlerFor($locale)->handle(new ConfigureAppLocale());

        $this->provideFullTestSet();
        $this->commandBus->dispatch(new BuildIndexHtml(SerializableDateTime::fromString('2023-10-17 16:15:04')));

        $fileSystem = $this->getContainer()->get('build_html.storage');
        foreach ($fileSystem->listContents('/', true) as $item) {
            $path = $item->path();

            $this->snapshotName = preg_replace('/[^a-zA-Z0-9]/', '-', (string) $path).'-'.$locale->value;
            if (!$item instanceof FileAttributes) {
                continue;
            }
            $this->assertMatchesHtmlSnapshot($fileSystem->read($path));
        }

        $this->assertEquals(
            $locale->value,
            $this->localeSwitcher->getLocale()
        );
        $this->assertEquals(
            $locale->value,
            Carbon::getLocale()
        );

        // Reset to default locale
        $this->configureLocaleHandlerFor(Locale::en_US)->handle(new ConfigureAppLocale());
    }

    public static function provideLocales(): array
    {
        return array_map(fn (Locale $locale): array => [$locale], Locale::cases());
    }

    protected function getSnapshotId(): string
    {
        return new \ReflectionClass($this)->getShortName().'--'.
            $this->name().'--'.
            $this->snapshotName;
    }

    private function configureLocaleHandlerFor(Locale $locale): ConfigureAppLocaleCommandHandler
    {
        $this->settingsRepository->save(SettingsGroup::APPEARANCE, [
            ...$this->settingsRepository->find(SettingsGroup::APPEARANCE),
            'locale' => $locale->value,
        ]);

        return new ConfigureAppLocaleCommandHandler($this->localeSwitcher, $this->settingsRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->localeSwitcher = $this->getContainer()->get(LocaleSwitcher::class);
        $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
