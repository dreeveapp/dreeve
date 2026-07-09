<?php

namespace App\Tests;

use App\Domain\Activity\ActivityIntensity;
use App\Domain\Activity\ActivityTotals;
use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Activity\Eddington\Eddington;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\Stream\StreamBasedActivityHeartRateRepository;
use App\Domain\Activity\Stream\StreamBasedActivityPowerRepository;
use App\Infrastructure\Twig\HtmlTwigExtension;
use Carbon\Carbon;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\LocaleSwitcher;

abstract class ContainerTestCase extends KernelTestCase
{
    use ProvideSettings;

    protected static ?Connection $ourDbalConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$ourDbalConnection instanceof Connection) {
            self::bootKernel();
            self::$ourDbalConnection = self::getContainer()->get(Connection::class);
        }

        // Empty the static cache between tests.
        EnrichedActivities::reset();
        DailyTrainingLoad::$cachedLoad = [];
        ActivityIntensity::$cachedIntensities = [];
        StreamBasedActivityPowerRepository::$cachedPowerOutputs = [];
        StreamBasedActivityHeartRateRepository::$cachedHeartRateZones = [];
        StreamBasedActivityHeartRateRepository::$cachedHeartRateZonesPerActivityType = [];
        StreamBasedActivityHeartRateRepository::$cachedHeartRateZonesPerActivity = [];
        StreamBasedActivityHeartRateRepository::$cachedHeartRateZonesInLastXDays = [];
        Eddington::$instances = [];
        ActivityTotals::$instance = null;
        HtmlTwigExtension::$seenIds = [];

        // Empty file systems.
        /** @var \League\Flysystem\FilesystemOperator[] $fileSystems */
        $fileSystems = [
            // $this->getContainer()->get('default.storage'),
            $this->getContainer()->get('file.storage'),
            $this->getContainer()->get('build_html.storage'),
            $this->getContainer()->get('build_api.storage'),
        ];

        foreach ($fileSystems as $fileSystem) {
            $fileSystem->deleteDirectory('/');
        }

        // Make sure every test is initialized with the default locale.
        /** @var LocaleSwitcher $localeSwitcher */
        $localeSwitcher = $this->getContainer()->get(LocaleSwitcher::class);
        $localeSwitcher->reset();
        Carbon::setLocale($localeSwitcher->getLocale());

        // Seed settings.
        $this->provideSettings();
    }

    protected function getConnection(): Connection
    {
        return self::$ourDbalConnection;
    }
}
