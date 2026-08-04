<?php

namespace App\Tests\Application\Build\ConfigureAppLocale;

use App\Application\Build\ConfigureAppLocale\ConfigureAppLocale;
use App\Application\Build\ConfigureAppLocale\ConfigureAppLocaleCommandHandler;
use App\Application\IndexPage;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Localisation\Locale;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Carbon\Carbon;
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
    private IndexPage $indexPage;

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

        $this->snapshotName = 'index-'.$locale->value;
        $this->assertMatchesHtmlSnapshot($this->indexPage->render());

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
        $this->indexPage = $this->getContainer()->get(IndexPage::class);
    }
}
