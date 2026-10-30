<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamTypes;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Challenge\ChallengeId;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDevice;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Domain\Integration\Weather\OpenMeteo\Weather;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\BestEffort\ActivityBestEffortBuilder;
use App\Tests\Domain\Activity\Lap\ActivityLapBuilder;
use App\Tests\Domain\Activity\Split\ActivitySplitBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamBuilder;
use App\Tests\Domain\Challenge\ChallengeBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use Money\Currency;
use Money\Money;
use Symfony\Component\DependencyInjection\Container;

trait ProvideTestData
{
    abstract protected static function getContainer(): Container;

    public function provideFullTestSet(): void
    {
        $this->addGeneralFixtures();
        $this->addGearFixtures();
        $this->addSegmentFixtures();
        $this->addChallengeFixtures();

        $this->addActivityOneFixtures();
        $this->addActivityTwoFixtures();
        $this->addActivityThreeFixtures();
        $this->addActivityFourFixtures();
        $this->addActivityFiveFixtures();
        $this->addActivitySixFixtures();
        $this->addActivitySevenFixtures();
        $this->addActivityEightFixtures();
        $this->addActivityNineFixtures();
        $this->addActivityTenFixtures();
        $this->addActivityElevenFixtures();
        $this->addActivityTwelveFixtures();
        $this->addActivityThirteenFixtures();
        $this->addActivityFourteenFixtures();
        $this->addActivityFifteenFixtures();
    }

    protected function addGeneralFixtures(): void
    {
        // The athlete is derived from the general settings baseline (see ProvideSettings).

        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            Key::THEME,
            Value::fromString(Json::encode($this->buildThemeConfig())),
        ));
    }

    protected function addGearFixtures(): void
    {
        /** @var GearRepository $gearRepository */
        $gearRepository = $this->getContainer()->get(GearRepository::class);
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('b12659861'))
                ->withCreatedOn(SerializableDateTime::fromString('2023-06-20 09:04:58'))
                ->withDistanceInMeter(Meter::from(1450147))
                ->withName('Retro Race Bike')
                ->withIsRetired(false)
                ->build()
        );
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('b12659862'))
                ->withCreatedOn(SerializableDateTime::fromString('2023-06-20 09:04:58'))
                ->withDistanceInMeter(Meter::from(145014))
                ->withName('Zwift hub')
                ->withIsRetired(false)
                ->build()
        );

        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('b12659562'))
                ->withCreatedOn(SerializableDateTime::fromString('2023-06-20 09:04:58'))
                ->withDistanceInMeter(Meter::from(100000))
                ->withName('Zwift One')
                ->withIsRetired(true)
                ->build()
        );

        /** @var RecordingDeviceRepository $recordingDeviceRepository */
        $recordingDeviceRepository = $this->getContainer()->get(RecordingDeviceRepository::class);
        $recordingDeviceRepository->save(
            RecordingDevice::create(
                name: 'Polar Vantage M',
                purchasePrice: new Money(29950, new Currency('EUR')),
            )
        );
    }

    protected function addSegmentWithAPolylineFixtures(): void
    {
        $segment = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('10'))
            ->withName(Name::fromString('Segment Ten'))
            ->withDistance(Kilometer::from(0.1))
            ->withDeviceName('MyWhoosh')
            ->withSportType(SportType::VIRTUAL_RIDE)
            ->withPolyline(EncodedPolyline::fromString('tqafAua~y^vG{D'))
            ->build();

        /** @var SegmentRepository $segmentRepository */
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        $segmentRepository->add($segment);
        $segmentRepository->update($segment);
    }

    private function addSegmentFixtures(): void
    {
        /** @var SegmentRepository $segmentRepository */
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        $segmentRepository->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withName(Name::fromString('Segment One'))
                ->withDistance(Kilometer::from(0.1))
                ->withMaxGradient(5.3)
                ->withIsFavourite(true)
                ->withDeviceName('Polar')
                ->withSportType(SportType::RIDE)
                ->build()
        );
        $segmentRepository->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('2'))
                ->withName(Name::fromString('Segment Two'))
                ->withDistance(Kilometer::from(0.11))
                ->withMaxGradient(1)
                ->withIsFavourite(false)
                ->withDeviceName('Polar')
                ->withSportType(SportType::RIDE)
                ->build()
        );
        $segmentRepository->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('17267489'))
                ->withName(Name::fromString('Alpe du Zwift'))
                ->withDistance(Kilometer::from(0.1))
                ->withMaxGradient(5.3)
                ->withIsFavourite(true)
                ->withDeviceName('Polar')
                ->withSportType(SportType::RIDE)
                ->build()
        );
    }

    private function addChallengeFixtures(): void
    {
        /** @var ChallengeRepository $challengeRepository */
        $challengeRepository = $this->getContainer()->get(ChallengeRepository::class);
        $challengeRepository->add(
            ChallengeBuilder::fromDefaults()
                ->withChallengeId(ChallengeId::fromUnprefixed('4022'))
                ->withCreatedOn(SerializableDateTime::fromString('2023-10-13 18:38:34'))
                ->withName('Castelli Keep It Up')
                ->withSlug('castelli-keep-it-up-2023')
                ->withLogoUrl('https://dgalywyr863hv.cloudfront.net/challenges/4022/4022-logo-100.png')
                ->withLocalLogoUrl('files/challenges/b7537b1e-69f7-11ee-a81e-0242a0cd5a47.png')
                ->build()
        );
        $challengeRepository->add(
            ChallengeBuilder::fromDefaults()
                ->withChallengeId(ChallengeId::fromUnprefixed('3809'))
                ->withCreatedOn(SerializableDateTime::fromString('2023-06-03 17:32:21'))
                ->withName('June Sweat With Pride Challenge')
                ->withSlug('June-Sweat-With-Pride-Challenge-2023')
                ->withLogoUrl('https://dgalywyr863hv.cloudfront.net/challenges/3809/3809-logo-100.png')
                ->withLocalLogoUrl('files/challenges/98b92ae4-0234-11ee-ad06-000d3a360b3f.png')
                ->build()
        );
    }

    private function addActivityOneFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-one.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withWeather(Weather::fromRawData(
                        Json::decode('{"latitude":51.199997,"longitude":3.199997,"generationtime_ms":1.9360780715942383,"utc_offset_seconds":7200,"timezone":"Europe/Brussels","timezone_abbreviation":"CEST","elevation":10,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relativehumidity_2m":"%","dewpoint_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","snowfall":"cm","weathercode":"wmo code","pressure_msl":"hPa","cloudcover":"%","cloudcover_low":"%","cloudcover_mid":"%","cloudcover_high":"%","et0_fao_evapotranspiration":"mm","vapor_pressure_deficit":"kPa","windspeed_10m":"km/h","windspeed_100m":"km/h","winddirection_10m":"°","winddirection_100m":"°","windgusts_10m":"km/h"},"hourly":{"time":["2023-07-29T00:00","2023-07-29T01:00","2023-07-29T02:00","2023-07-29T03:00","2023-07-29T04:00","2023-07-29T05:00","2023-07-29T06:00","2023-07-29T07:00","2023-07-29T08:00","2023-07-29T09:00","2023-07-29T10:00","2023-07-29T11:00","2023-07-29T12:00","2023-07-29T13:00","2023-07-29T14:00","2023-07-29T15:00","2023-07-29T16:00","2023-07-29T17:00","2023-07-29T18:00","2023-07-29T19:00","2023-07-29T20:00","2023-07-29T21:00","2023-07-29T22:00","2023-07-29T23:00"],"temperature_2m":[17.3,16.8,16.3,16.1,16.1,16.2,16.2,16.5,17.1,18,18.5,18.9,18.8,19.6,20.8,21.3,21.3,20.6,20.7,20.2,19.4,18.7,17.6,16.9],"relativehumidity_2m":[82,83,85,86,87,88,89,88,86,82,80,79,81,81,78,75,70,67,62,63,68,67,72,77],"dewpoint_2m":[14.3,13.9,13.8,13.8,14,14.2,14.3,14.5,14.8,14.9,15,15.1,15.5,16.2,16.8,16.8,15.7,14.3,13.3,12.9,13.2,12.6,12.6,12.7],"apparent_temperature":[16.1,15.7,15.3,15.2,15,14.8,14.7,15,15.9,16.7,17.2,17.5,17.6,18.6,20,20.4,19.5,18.3,18,17.3,17,16.2,15.2,14.5],"precipitation":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"rain":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"snowfall":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"weathercode":[1,1,1,1,3,3,2,3,51,51,51,51,51,51,51,51,51,51,0,0,1,1,1,0],"pressure_msl":[1007.8,1007.6,1007.8,1007.2,1007.2,1007.1,1007.1,1007.1,1007.1,1007.4,1007.2,1007.4,1007.5,1007.7,1007.5,1007.5,1007.4,1007.6,1007.5,1007.6,1008.1,1008.4,1008.9,1009.2],"cloudcover":[30,30,31,37,83,100,79,100,100,100,100,100,74,45,56,48,32,22,8,11,32,32,31,13],"cloudcover_low":[1,0,1,3,16,44,53,77,99,98,100,97,65,31,36,32,15,9,6,6,9,8,12,10],"cloudcover_mid":[0,0,0,7,65,88,41,59,42,67,92,93,17,23,36,31,30,23,4,9,14,6,2,2],"cloudcover_high":[98,99,100,99,97,97,21,3,22,35,34,48,18,11,7,2,2,0,0,2,52,69,63,10],"et0_fao_evapotranspiration":[0.03,0.02,0.02,0.01,0.01,0.01,0.01,0.03,0.06,0.11,0.14,0.15,0.15,0.23,0.33,0.33,0.31,0.3,0.31,0.24,0.17,0.11,0.07,0.05],"vapor_pressure_deficit":[0.35,0.32,0.28,0.25,0.23,0.22,0.21,0.22,0.27,0.37,0.42,0.46,0.4,0.43,0.54,0.63,0.75,0.8,0.92,0.87,0.73,0.7,0.56,0.45],"windspeed_10m":[17.8,15.7,15.3,15.1,16.3,18.8,19.9,20.1,19.1,20.1,20.6,21.1,20.9,21.3,23.2,24,25,25.8,26.1,26.2,23.5,22.6,21.8,21.9],"windspeed_100m":[29.9,26.9,26.3,26,27.3,29.9,30.8,30.7,29.3,28,28,30,28.5,27.8,30.7,31.9,33.4,34.9,36.3,36.6,34.7,34.9,34.6,34.7],"winddirection_10m":[234,233,221,220,221,225,228,227,226,224,223,226,238,236,237,240,247,243,246,249,245,239,236,234],"winddirection_100m":[239,240,230,228,228,229,232,230,227,225,223,226,238,236,236,240,248,244,246,249,246,242,239,237],"windgusts_10m":[27,27,27,27,28.8,30.2,31.7,32,31.3,38.5,41.4,42.8,41.4,45.4,49.3,55.1,52.9,44.6,45.4,43.9,39.2,37.1,35.6,35.3]},"daily_units":{"time":"iso8601","weathercode":"wmo code","temperature_2m_max":"°C","temperature_2m_min":"°C","temperature_2m_mean":"°C","apparent_temperature_max":"°C","apparent_temperature_min":"°C","apparent_temperature_mean":"°C","sunrise":"iso8601","sunset":"iso8601","precipitation_sum":"mm","rain_sum":"mm","snowfall_sum":"cm","precipitation_hours":"h","windspeed_10m_max":"km/h","windgusts_10m_max":"km/h","winddirection_10m_dominant":"°","shortwave_radiation_sum":"MJ/m²","et0_fao_evapotranspiration":"mm"},"daily":{"time":["2023-07-29"],"weathercode":[51],"temperature_2m_max":[21.3],"temperature_2m_min":[16.1],"temperature_2m_mean":[18.3],"apparent_temperature_max":[20.4],"apparent_temperature_min":[14.5],"apparent_temperature_mean":[16.7],"sunrise":["2023-07-29T06:07"],"sunset":["2023-07-29T21:39"],"precipitation_sum":[1.3],"rain_sum":[1.3],"snowfall_sum":[0],"precipitation_hours":[10],"windspeed_10m_max":[26.2],"windgusts_10m_max":[55.1],"winddirection_10m_dominant":[234],"shortwave_radiation_sum":[16.77],"et0_fao_evapotranspiration":[3.2]}}'),
                        SerializableDateTime::fromString('2023-07-29 07:34:03')
                    ));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var ActivityBestEffortRepository $activityBestEffortRepository */
        $activityBestEffortRepository = $this->getContainer()->get(ActivityBestEffortRepository::class);
        foreach (ActivityType::RIDE->getDistancesForBestEffortCalculation() as $distance) {
            $activityBestEffortRepository->add(
                ActivityBestEffortBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('95427823814'))
                    ->withSportType(SportType::RIDE)
                    ->withDistanceInMeter($distance->toMeter())
                    ->withTimeInSeconds($distance->toInt())
                    ->build()
            );
        }
    }

    private function addActivityTwoFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-two.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withWeather(Weather::fromRawData(
                        Json::decode('{"latitude":51.199997,"longitude":3.199997,"generationtime_ms":1.9360780715942383,"utc_offset_seconds":7200,"timezone":"Europe/Brussels","timezone_abbreviation":"CEST","elevation":10,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relativehumidity_2m":"%","dewpoint_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","snowfall":"cm","weathercode":"wmo code","pressure_msl":"hPa","cloudcover":"%","cloudcover_low":"%","cloudcover_mid":"%","cloudcover_high":"%","et0_fao_evapotranspiration":"mm","vapor_pressure_deficit":"kPa","windspeed_10m":"km/h","windspeed_100m":"km/h","winddirection_10m":"°","winddirection_100m":"°","windgusts_10m":"km/h"},"hourly":{"time":["2023-07-29T00:00","2023-07-29T01:00","2023-07-29T02:00","2023-07-29T03:00","2023-07-29T04:00","2023-07-29T05:00","2023-07-29T06:00","2023-07-29T07:00","2023-07-29T08:00","2023-07-29T09:00","2023-07-29T10:00","2023-07-29T11:00","2023-07-29T12:00","2023-07-29T13:00","2023-07-29T14:00","2023-07-29T15:00","2023-07-29T16:00","2023-07-29T17:00","2023-07-29T18:00","2023-07-29T19:00","2023-07-29T20:00","2023-07-29T21:00","2023-07-29T22:00","2023-07-29T23:00"],"temperature_2m":[17.3,16.8,16.3,16.1,16.1,16.2,16.2,16.5,17.1,18,18.5,18.9,18.8,19.6,20.8,21.3,21.3,20.6,20.7,20.2,19.4,18.7,17.6,16.9],"relativehumidity_2m":[82,83,85,86,87,88,89,88,86,82,80,79,81,81,78,75,70,67,62,63,68,67,72,77],"dewpoint_2m":[14.3,13.9,13.8,13.8,14,14.2,14.3,14.5,14.8,14.9,15,15.1,15.5,16.2,16.8,16.8,15.7,14.3,13.3,12.9,13.2,12.6,12.6,12.7],"apparent_temperature":[16.1,15.7,15.3,15.2,15,14.8,14.7,15,15.9,16.7,17.2,17.5,17.6,18.6,20,20.4,19.5,18.3,18,17.3,17,16.2,15.2,14.5],"precipitation":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"rain":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"snowfall":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"weathercode":[1,1,1,1,3,3,2,3,51,51,51,51,51,51,51,51,51,51,0,0,1,1,1,0],"pressure_msl":[1007.8,1007.6,1007.8,1007.2,1007.2,1007.1,1007.1,1007.1,1007.1,1007.4,1007.2,1007.4,1007.5,1007.7,1007.5,1007.5,1007.4,1007.6,1007.5,1007.6,1008.1,1008.4,1008.9,1009.2],"cloudcover":[30,30,31,37,83,100,79,100,100,100,100,100,74,45,56,48,32,22,8,11,32,32,31,13],"cloudcover_low":[1,0,1,3,16,44,53,77,99,98,100,97,65,31,36,32,15,9,6,6,9,8,12,10],"cloudcover_mid":[0,0,0,7,65,88,41,59,42,67,92,93,17,23,36,31,30,23,4,9,14,6,2,2],"cloudcover_high":[98,99,100,99,97,97,21,3,22,35,34,48,18,11,7,2,2,0,0,2,52,69,63,10],"et0_fao_evapotranspiration":[0.03,0.02,0.02,0.01,0.01,0.01,0.01,0.03,0.06,0.11,0.14,0.15,0.15,0.23,0.33,0.33,0.31,0.3,0.31,0.24,0.17,0.11,0.07,0.05],"vapor_pressure_deficit":[0.35,0.32,0.28,0.25,0.23,0.22,0.21,0.22,0.27,0.37,0.42,0.46,0.4,0.43,0.54,0.63,0.75,0.8,0.92,0.87,0.73,0.7,0.56,0.45],"windspeed_10m":[17.8,15.7,15.3,15.1,16.3,18.8,19.9,20.1,19.1,20.1,20.6,21.1,20.9,21.3,23.2,24,25,25.8,26.1,26.2,23.5,22.6,21.8,21.9],"windspeed_100m":[29.9,26.9,26.3,26,27.3,29.9,30.8,30.7,29.3,28,28,30,28.5,27.8,30.7,31.9,33.4,34.9,36.3,36.6,34.7,34.9,34.6,34.7],"winddirection_10m":[234,233,221,220,221,225,228,227,226,224,223,226,238,236,237,240,247,243,246,249,245,239,236,234],"winddirection_100m":[239,240,230,228,228,229,232,230,227,225,223,226,238,236,236,240,248,244,246,249,246,242,239,237],"windgusts_10m":[27,27,27,27,28.8,30.2,31.7,32,31.3,38.5,41.4,42.8,41.4,45.4,49.3,55.1,52.9,44.6,45.4,43.9,39.2,37.1,35.6,35.3]},"daily_units":{"time":"iso8601","weathercode":"wmo code","temperature_2m_max":"°C","temperature_2m_min":"°C","temperature_2m_mean":"°C","apparent_temperature_max":"°C","apparent_temperature_min":"°C","apparent_temperature_mean":"°C","sunrise":"iso8601","sunset":"iso8601","precipitation_sum":"mm","rain_sum":"mm","snowfall_sum":"cm","precipitation_hours":"h","windspeed_10m_max":"km/h","windgusts_10m_max":"km/h","winddirection_10m_dominant":"°","shortwave_radiation_sum":"MJ/m²","et0_fao_evapotranspiration":"mm"},"daily":{"time":["2023-07-29"],"weathercode":[51],"temperature_2m_max":[21.3],"temperature_2m_min":[16.1],"temperature_2m_mean":[18.3],"apparent_temperature_max":[20.4],"apparent_temperature_min":[14.5],"apparent_temperature_mean":[16.7],"sunrise":["2023-07-29T06:07"],"sunset":["2023-07-29T21:39"],"precipitation_sum":[1.3],"rain_sum":[1.3],"snowfall_sum":[0],"precipitation_hours":[10],"windspeed_10m_max":[26.2],"windgusts_10m_max":[55.1],"winddirection_10m_dominant":[234],"shortwave_radiation_sum":[16.77],"et0_fao_evapotranspiration":[3.2]}}'),
                        SerializableDateTime::fromString('2023-07-29 09:34:03')
                    ));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));
    }

    private function addActivityThreeFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-three.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withWeather(Weather::fromRawData(
                        Json::decode('{"latitude":51.199997,"longitude":3.199997,"generationtime_ms":1.9360780715942383,"utc_offset_seconds":7200,"timezone":"Europe/Brussels","timezone_abbreviation":"CEST","elevation":10,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relativehumidity_2m":"%","dewpoint_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","snowfall":"cm","weathercode":"wmo code","pressure_msl":"hPa","cloudcover":"%","cloudcover_low":"%","cloudcover_mid":"%","cloudcover_high":"%","et0_fao_evapotranspiration":"mm","vapor_pressure_deficit":"kPa","windspeed_10m":"km/h","windspeed_100m":"km/h","winddirection_10m":"°","winddirection_100m":"°","windgusts_10m":"km/h"},"hourly":{"time":["2023-07-29T00:00","2023-07-29T01:00","2023-07-29T02:00","2023-07-29T03:00","2023-07-29T04:00","2023-07-29T05:00","2023-07-29T06:00","2023-07-29T07:00","2023-07-29T08:00","2023-07-29T09:00","2023-07-29T10:00","2023-07-29T11:00","2023-07-29T12:00","2023-07-29T13:00","2023-07-29T14:00","2023-07-29T15:00","2023-07-29T16:00","2023-07-29T17:00","2023-07-29T18:00","2023-07-29T19:00","2023-07-29T20:00","2023-07-29T21:00","2023-07-29T22:00","2023-07-29T23:00"],"temperature_2m":[17.3,16.8,16.3,16.1,16.1,16.2,16.2,16.5,17.1,18,18.5,18.9,18.8,19.6,20.8,21.3,21.3,20.6,20.7,20.2,19.4,18.7,17.6,16.9],"relativehumidity_2m":[82,83,85,86,87,88,89,88,86,82,80,79,81,81,78,75,70,67,62,63,68,67,72,77],"dewpoint_2m":[14.3,13.9,13.8,13.8,14,14.2,14.3,14.5,14.8,14.9,15,15.1,15.5,16.2,16.8,16.8,15.7,14.3,13.3,12.9,13.2,12.6,12.6,12.7],"apparent_temperature":[16.1,15.7,15.3,15.2,15,14.8,14.7,15,15.9,16.7,17.2,17.5,17.6,18.6,20,20.4,19.5,18.3,18,17.3,17,16.2,15.2,14.5],"precipitation":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"rain":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"snowfall":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"weathercode":[1,1,1,1,3,3,2,3,51,51,51,51,51,51,51,51,51,51,0,0,1,1,1,0],"pressure_msl":[1007.8,1007.6,1007.8,1007.2,1007.2,1007.1,1007.1,1007.1,1007.1,1007.4,1007.2,1007.4,1007.5,1007.7,1007.5,1007.5,1007.4,1007.6,1007.5,1007.6,1008.1,1008.4,1008.9,1009.2],"cloudcover":[30,30,31,37,83,100,79,100,100,100,100,100,74,45,56,48,32,22,8,11,32,32,31,13],"cloudcover_low":[1,0,1,3,16,44,53,77,99,98,100,97,65,31,36,32,15,9,6,6,9,8,12,10],"cloudcover_mid":[0,0,0,7,65,88,41,59,42,67,92,93,17,23,36,31,30,23,4,9,14,6,2,2],"cloudcover_high":[98,99,100,99,97,97,21,3,22,35,34,48,18,11,7,2,2,0,0,2,52,69,63,10],"et0_fao_evapotranspiration":[0.03,0.02,0.02,0.01,0.01,0.01,0.01,0.03,0.06,0.11,0.14,0.15,0.15,0.23,0.33,0.33,0.31,0.3,0.31,0.24,0.17,0.11,0.07,0.05],"vapor_pressure_deficit":[0.35,0.32,0.28,0.25,0.23,0.22,0.21,0.22,0.27,0.37,0.42,0.46,0.4,0.43,0.54,0.63,0.75,0.8,0.92,0.87,0.73,0.7,0.56,0.45],"windspeed_10m":[17.8,15.7,15.3,15.1,16.3,18.8,19.9,20.1,19.1,20.1,20.6,21.1,20.9,21.3,23.2,24,25,25.8,26.1,26.2,23.5,22.6,21.8,21.9],"windspeed_100m":[29.9,26.9,26.3,26,27.3,29.9,30.8,30.7,29.3,28,28,30,28.5,27.8,30.7,31.9,33.4,34.9,36.3,36.6,34.7,34.9,34.6,34.7],"winddirection_10m":[234,233,221,220,221,225,228,227,226,224,223,226,238,236,237,240,247,243,246,249,245,239,236,234],"winddirection_100m":[239,240,230,228,228,229,232,230,227,225,223,226,238,236,236,240,248,244,246,249,246,242,239,237],"windgusts_10m":[27,27,27,27,28.8,30.2,31.7,32,31.3,38.5,41.4,42.8,41.4,45.4,49.3,55.1,52.9,44.6,45.4,43.9,39.2,37.1,35.6,35.3]},"daily_units":{"time":"iso8601","weathercode":"wmo code","temperature_2m_max":"°C","temperature_2m_min":"°C","temperature_2m_mean":"°C","apparent_temperature_max":"°C","apparent_temperature_min":"°C","apparent_temperature_mean":"°C","sunrise":"iso8601","sunset":"iso8601","precipitation_sum":"mm","rain_sum":"mm","snowfall_sum":"cm","precipitation_hours":"h","windspeed_10m_max":"km/h","windgusts_10m_max":"km/h","winddirection_10m_dominant":"°","shortwave_radiation_sum":"MJ/m²","et0_fao_evapotranspiration":"mm"},"daily":{"time":["2023-07-29"],"weathercode":[51],"temperature_2m_max":[21.3],"temperature_2m_min":[16.1],"temperature_2m_mean":[18.3],"apparent_temperature_max":[20.4],"apparent_temperature_min":[14.5],"apparent_temperature_mean":[16.7],"sunrise":["2023-07-29T06:07"],"sunset":["2023-07-29T21:39"],"precipitation_sum":[1.3],"rain_sum":[1.3],"snowfall_sum":[0],"precipitation_hours":[10],"windspeed_10m_max":[26.2],"windgusts_10m_max":[55.1],"winddirection_10m_dominant":[234],"shortwave_radiation_sum":[16.77],"et0_fao_evapotranspiration":[3.2]}}'),
                        SerializableDateTime::fromString('2023-07-28 09:34:03')
                    ));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var SegmentEffortRepository $segmentEffortRepository */
        $segmentEffortRepository = $this->getContainer()->get(SegmentEffortRepository::class);

        $segmentEffortRepository->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('1'))
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                ->withElapsedTimeInSeconds(10.3)
                ->withAverageWatts(200)
                ->withDistance(Kilometer::from(0.1))
                ->build()
        );
        $segmentEffortRepository->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('alpe-du-zwift'))
                ->withSegmentId(SegmentId::fromUnprefixed('17267489'))
                ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                ->withElapsedTimeInSeconds(10.3)
                ->withAverageWatts(200)
                ->withDistance(Kilometer::from(0.1))
                ->build()
        );
        $segmentEffortRepository->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('2'))
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                ->withElapsedTimeInSeconds(9.3)
                ->withAverageWatts(200)
                ->withDistance(Kilometer::from(0.1))
                ->build()
        );

        $segmentEffortRepository->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('3'))
                ->withSegmentId(SegmentId::fromUnprefixed('2'))
                ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                ->withElapsedTimeInSeconds(10.3)
                ->withDistance(Kilometer::from(0.1))
                ->build()
        );

        /** @var ActivityBestEffortRepository $activityBestEffortRepository */
        $activityBestEffortRepository = $this->getContainer()->get(ActivityBestEffortRepository::class);
        foreach (ActivityType::RIDE->getDistancesForBestEffortCalculation() as $distance) {
            $activityBestEffortRepository->add(
                ActivityBestEffortBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                    ->withSportType(SportType::VIRTUAL_RIDE)
                    ->withDistanceInMeter($distance->toMeter())
                    ->withTimeInSeconds($distance->toInt())
                    ->build()
            );
        }
    }

    private function addActivityFourFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-four.json'));
        $activity = Activity::createFromRawStravaData($rawData)
            ->withRouteGeography(RouteGeography::create(['state' => 'watopia']))
            ->withLocalImagePaths(['files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg']);

        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var ActivityStreamRepository $activityStreamRepository */
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('9756441741'))
                ->withStreamType(StreamType::WATTS)
                ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/watts.json')))
                ->build()
        );
        $cadenceStreamFor9756441741 = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('9756441741'))
            ->withStreamType(StreamType::CADENCE)
            ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
            ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/cadence.json')))
            ->build();
        $activityStreamRepository->add($cadenceStreamFor9756441741);
        $heartRateStreamFor9756441741 = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('9756441741'))
            ->withStreamType(StreamType::HEART_RATE)
            ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
            ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/heart-rate.json')))
            ->build();
        $activityStreamRepository->add($heartRateStreamFor9756441741);
        $activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('9756441741'))
                ->withStreamType(StreamType::TIME)
                ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/heart-rate.json')))
                ->build()
        );

        /** @var CombinedActivityStreamRepository $combinedActivityStreamRepository */
        $combinedActivityStreamRepository = $this->getContainer()->get(CombinedActivityStreamRepository::class);
        $combinedActivityStreamRepository->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('9756441741'))
                ->withUnitSystem(UnitSystem::METRIC)
                ->withStreamTypes(CombinedStreamTypes::fromArray([
                    CombinedStreamType::TIME,
                    CombinedStreamType::DISTANCE,
                    CombinedStreamType::ALTITUDE,
                    CombinedStreamType::GRADE,
                    CombinedStreamType::HEART_RATE,
                    CombinedStreamType::VELOCITY,
                    CombinedStreamType::WATTS]))
                ->withData([[1, 2, 3, 4, 5, 6, 7], [2, 2, 3, 4, 5, 6, 7], [3, 2, 3, 4, 5, 6, 7], [4, 2, 3, 4, 5, 6, 7], [5, 2, 3, 4, 5, 6, 7]])
                ->build()
        );

        $activityStreamMetricRepository = $this->getContainer()->get(ActivityStreamMetricRepository::class);
        $bestAveragesWatts = Json::decode('{"1":540,"5":493,"10":460,"15":442,"30":412,"45":373,"60":352,"120":293,"180":273,"240":282,"300":273,"390":262,"480":256,"720":246,"960":239,"1200":239,"1800":225,"2400":222,"3000":222,"3600":218}');
        $bestAveragesCadence = Json::decode('{"1":102,"5":101,"10":100,"15":100,"30":99,"45":99,"60":99,"120":97,"180":96,"240":96,"300":95,"390":94,"480":93,"720":93,"960":92,"1200":93,"1800":89,"2400":86,"3000":85,"3600":84}');
        $bestAveragesHeartRate = Json::decode('{"1":190,"5":189,"10":189,"15":189,"30":189,"45":188,"60":188,"120":185,"180":183,"240":184,"300":183,"390":182,"480":182,"720":181,"960":180,"1200":180,"1800":178,"2400":178,"3000":178,"3600":177}');

        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441741'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: $bestAveragesWatts,
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441741'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [1 => 2, 2 => 5, 6 => 8],
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441741'),
            streamType: StreamType::CADENCE,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: $bestAveragesCadence,
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441741'),
            streamType: StreamType::CADENCE,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: self::valueDistributionFor($cadenceStreamFor9756441741),
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441741'),
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: $bestAveragesHeartRate,
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441741'),
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: self::valueDistributionFor($heartRateStreamFor9756441741),
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441741'),
            streamType: StreamType::TIME,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: $bestAveragesHeartRate,
        ));
    }

    private function addActivityFiveFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-five.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withWeather(Weather::fromRawData(
                        Json::decode('{"latitude":51.199997,"longitude":3.199997,"generationtime_ms":1.9360780715942383,"utc_offset_seconds":7200,"timezone":"Europe/Brussels","timezone_abbreviation":"CEST","elevation":10,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relativehumidity_2m":"%","dewpoint_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","snowfall":"cm","weathercode":"wmo code","pressure_msl":"hPa","cloudcover":"%","cloudcover_low":"%","cloudcover_mid":"%","cloudcover_high":"%","et0_fao_evapotranspiration":"mm","vapor_pressure_deficit":"kPa","windspeed_10m":"km/h","windspeed_100m":"km/h","winddirection_10m":"°","winddirection_100m":"°","windgusts_10m":"km/h"},"hourly":{"time":["2023-07-29T00:00","2023-07-29T01:00","2023-07-29T02:00","2023-07-29T03:00","2023-07-29T04:00","2023-07-29T05:00","2023-07-29T06:00","2023-07-29T07:00","2023-07-29T08:00","2023-07-29T09:00","2023-07-29T10:00","2023-07-29T11:00","2023-07-29T12:00","2023-07-29T13:00","2023-07-29T14:00","2023-07-29T15:00","2023-07-29T16:00","2023-07-29T17:00","2023-07-29T18:00","2023-07-29T19:00","2023-07-29T20:00","2023-07-29T21:00","2023-07-29T22:00","2023-07-29T23:00"],"temperature_2m":[17.3,16.8,16.3,16.1,16.1,16.2,16.2,16.5,17.1,18,18.5,18.9,18.8,19.6,20.8,21.3,21.3,20.6,20.7,20.2,19.4,18.7,17.6,16.9],"relativehumidity_2m":[82,83,85,86,87,88,89,88,86,82,80,79,81,81,78,75,70,67,62,63,68,67,72,77],"dewpoint_2m":[14.3,13.9,13.8,13.8,14,14.2,14.3,14.5,14.8,14.9,15,15.1,15.5,16.2,16.8,16.8,15.7,14.3,13.3,12.9,13.2,12.6,12.6,12.7],"apparent_temperature":[16.1,15.7,15.3,15.2,15,14.8,14.7,15,15.9,16.7,17.2,17.5,17.6,18.6,20,20.4,19.5,18.3,18,17.3,17,16.2,15.2,14.5],"precipitation":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"rain":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"snowfall":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"weathercode":[1,1,1,1,3,3,2,3,51,51,51,51,51,51,51,51,51,51,0,0,1,1,1,0],"pressure_msl":[1007.8,1007.6,1007.8,1007.2,1007.2,1007.1,1007.1,1007.1,1007.1,1007.4,1007.2,1007.4,1007.5,1007.7,1007.5,1007.5,1007.4,1007.6,1007.5,1007.6,1008.1,1008.4,1008.9,1009.2],"cloudcover":[30,30,31,37,83,100,79,100,100,100,100,100,74,45,56,48,32,22,8,11,32,32,31,13],"cloudcover_low":[1,0,1,3,16,44,53,77,99,98,100,97,65,31,36,32,15,9,6,6,9,8,12,10],"cloudcover_mid":[0,0,0,7,65,88,41,59,42,67,92,93,17,23,36,31,30,23,4,9,14,6,2,2],"cloudcover_high":[98,99,100,99,97,97,21,3,22,35,34,48,18,11,7,2,2,0,0,2,52,69,63,10],"et0_fao_evapotranspiration":[0.03,0.02,0.02,0.01,0.01,0.01,0.01,0.03,0.06,0.11,0.14,0.15,0.15,0.23,0.33,0.33,0.31,0.3,0.31,0.24,0.17,0.11,0.07,0.05],"vapor_pressure_deficit":[0.35,0.32,0.28,0.25,0.23,0.22,0.21,0.22,0.27,0.37,0.42,0.46,0.4,0.43,0.54,0.63,0.75,0.8,0.92,0.87,0.73,0.7,0.56,0.45],"windspeed_10m":[17.8,15.7,15.3,15.1,16.3,18.8,19.9,20.1,19.1,20.1,20.6,21.1,20.9,21.3,23.2,24,25,25.8,26.1,26.2,23.5,22.6,21.8,21.9],"windspeed_100m":[29.9,26.9,26.3,26,27.3,29.9,30.8,30.7,29.3,28,28,30,28.5,27.8,30.7,31.9,33.4,34.9,36.3,36.6,34.7,34.9,34.6,34.7],"winddirection_10m":[234,233,221,220,221,225,228,227,226,224,223,226,238,236,237,240,247,243,246,249,245,239,236,234],"winddirection_100m":[239,240,230,228,228,229,232,230,227,225,223,226,238,236,236,240,248,244,246,249,246,242,239,237],"windgusts_10m":[27,27,27,27,28.8,30.2,31.7,32,31.3,38.5,41.4,42.8,41.4,45.4,49.3,55.1,52.9,44.6,45.4,43.9,39.2,37.1,35.6,35.3]},"daily_units":{"time":"iso8601","weathercode":"wmo code","temperature_2m_max":"°C","temperature_2m_min":"°C","temperature_2m_mean":"°C","apparent_temperature_max":"°C","apparent_temperature_min":"°C","apparent_temperature_mean":"°C","sunrise":"iso8601","sunset":"iso8601","precipitation_sum":"mm","rain_sum":"mm","snowfall_sum":"cm","precipitation_hours":"h","windspeed_10m_max":"km/h","windgusts_10m_max":"km/h","winddirection_10m_dominant":"°","shortwave_radiation_sum":"MJ/m²","et0_fao_evapotranspiration":"mm"},"daily":{"time":["2023-07-29"],"weathercode":[51],"temperature_2m_max":[21.3],"temperature_2m_min":[16.1],"temperature_2m_mean":[18.3],"apparent_temperature_max":[20.4],"apparent_temperature_min":[14.5],"apparent_temperature_mean":[16.7],"sunrise":["2023-07-29T06:07"],"sunset":["2023-07-29T21:39"],"precipitation_sum":[1.3],"rain_sum":[1.3],"snowfall_sum":[0],"precipitation_hours":[10],"windspeed_10m_max":[26.2],"windgusts_10m_max":[55.1],"winddirection_10m_dominant":[234],"shortwave_radiation_sum":[16.77],"et0_fao_evapotranspiration":[3.2]}}'),
                        SerializableDateTime::fromString('2023-09-04 21:05:38')
                    ));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));
    }

    private function addActivitySixFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-six.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withWeather(Weather::fromRawData(
                        Json::decode('{"latitude":51.199997,"longitude":3.199997,"generationtime_ms":1.9360780715942383,"utc_offset_seconds":7200,"timezone":"Europe/Brussels","timezone_abbreviation":"CEST","elevation":10,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relativehumidity_2m":"%","dewpoint_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","snowfall":"cm","weathercode":"wmo code","pressure_msl":"hPa","cloudcover":"%","cloudcover_low":"%","cloudcover_mid":"%","cloudcover_high":"%","et0_fao_evapotranspiration":"mm","vapor_pressure_deficit":"kPa","windspeed_10m":"km/h","windspeed_100m":"km/h","winddirection_10m":"°","winddirection_100m":"°","windgusts_10m":"km/h"},"hourly":{"time":["2023-07-29T00:00","2023-07-29T01:00","2023-07-29T02:00","2023-07-29T03:00","2023-07-29T04:00","2023-07-29T05:00","2023-07-29T06:00","2023-07-29T07:00","2023-07-29T08:00","2023-07-29T09:00","2023-07-29T10:00","2023-07-29T11:00","2023-07-29T12:00","2023-07-29T13:00","2023-07-29T14:00","2023-07-29T15:00","2023-07-29T16:00","2023-07-29T17:00","2023-07-29T18:00","2023-07-29T19:00","2023-07-29T20:00","2023-07-29T21:00","2023-07-29T22:00","2023-07-29T23:00"],"temperature_2m":[17.3,16.8,16.3,16.1,16.1,16.2,16.2,16.5,17.1,18,18.5,18.9,18.8,19.6,20.8,21.3,21.3,20.6,20.7,20.2,19.4,18.7,17.6,16.9],"relativehumidity_2m":[82,83,85,86,87,88,89,88,86,82,80,79,81,81,78,75,70,67,62,63,68,67,72,77],"dewpoint_2m":[14.3,13.9,13.8,13.8,14,14.2,14.3,14.5,14.8,14.9,15,15.1,15.5,16.2,16.8,16.8,15.7,14.3,13.3,12.9,13.2,12.6,12.6,12.7],"apparent_temperature":[16.1,15.7,15.3,15.2,15,14.8,14.7,15,15.9,16.7,17.2,17.5,17.6,18.6,20,20.4,19.5,18.3,18,17.3,17,16.2,15.2,14.5],"precipitation":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"rain":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"snowfall":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"weathercode":[1,1,1,1,3,3,2,3,51,51,51,51,51,51,51,51,51,51,0,0,1,1,1,0],"pressure_msl":[1007.8,1007.6,1007.8,1007.2,1007.2,1007.1,1007.1,1007.1,1007.1,1007.4,1007.2,1007.4,1007.5,1007.7,1007.5,1007.5,1007.4,1007.6,1007.5,1007.6,1008.1,1008.4,1008.9,1009.2],"cloudcover":[30,30,31,37,83,100,79,100,100,100,100,100,74,45,56,48,32,22,8,11,32,32,31,13],"cloudcover_low":[1,0,1,3,16,44,53,77,99,98,100,97,65,31,36,32,15,9,6,6,9,8,12,10],"cloudcover_mid":[0,0,0,7,65,88,41,59,42,67,92,93,17,23,36,31,30,23,4,9,14,6,2,2],"cloudcover_high":[98,99,100,99,97,97,21,3,22,35,34,48,18,11,7,2,2,0,0,2,52,69,63,10],"et0_fao_evapotranspiration":[0.03,0.02,0.02,0.01,0.01,0.01,0.01,0.03,0.06,0.11,0.14,0.15,0.15,0.23,0.33,0.33,0.31,0.3,0.31,0.24,0.17,0.11,0.07,0.05],"vapor_pressure_deficit":[0.35,0.32,0.28,0.25,0.23,0.22,0.21,0.22,0.27,0.37,0.42,0.46,0.4,0.43,0.54,0.63,0.75,0.8,0.92,0.87,0.73,0.7,0.56,0.45],"windspeed_10m":[17.8,15.7,15.3,15.1,16.3,18.8,19.9,20.1,19.1,20.1,20.6,21.1,20.9,21.3,23.2,24,25,25.8,26.1,26.2,23.5,22.6,21.8,21.9],"windspeed_100m":[29.9,26.9,26.3,26,27.3,29.9,30.8,30.7,29.3,28,28,30,28.5,27.8,30.7,31.9,33.4,34.9,36.3,36.6,34.7,34.9,34.6,34.7],"winddirection_10m":[234,233,221,220,221,225,228,227,226,224,223,226,238,236,237,240,247,243,246,249,245,239,236,234],"winddirection_100m":[239,240,230,228,228,229,232,230,227,225,223,226,238,236,236,240,248,244,246,249,246,242,239,237],"windgusts_10m":[27,27,27,27,28.8,30.2,31.7,32,31.3,38.5,41.4,42.8,41.4,45.4,49.3,55.1,52.9,44.6,45.4,43.9,39.2,37.1,35.6,35.3]},"daily_units":{"time":"iso8601","weathercode":"wmo code","temperature_2m_max":"°C","temperature_2m_min":"°C","temperature_2m_mean":"°C","apparent_temperature_max":"°C","apparent_temperature_min":"°C","apparent_temperature_mean":"°C","sunrise":"iso8601","sunset":"iso8601","precipitation_sum":"mm","rain_sum":"mm","snowfall_sum":"cm","precipitation_hours":"h","windspeed_10m_max":"km/h","windgusts_10m_max":"km/h","winddirection_10m_dominant":"°","shortwave_radiation_sum":"MJ/m²","et0_fao_evapotranspiration":"mm"},"daily":{"time":["2023-07-29"],"weathercode":[51],"temperature_2m_max":[21.3],"temperature_2m_min":[16.1],"temperature_2m_mean":[18.3],"apparent_temperature_max":[20.4],"apparent_temperature_min":[14.5],"apparent_temperature_mean":[16.7],"sunrise":["2023-07-29T06:07"],"sunset":["2023-07-29T21:39"],"precipitation_sum":[1.3],"rain_sum":[1.3],"snowfall_sum":[0],"precipitation_hours":[10],"windspeed_10m_max":[26.2],"windgusts_10m_max":[55.1],"winddirection_10m_dominant":[234],"shortwave_radiation_sum":[16.77],"et0_fao_evapotranspiration":[3.2]}}'),
                        SerializableDateTime::fromString('2023-09-05 21:05:38')
                    ))
                    ->withRouteGeography(RouteGeography::create(['country_code' => 'be', 'state' => 'West-Vlaanderen', 'city' => 'Brugge']));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));
    }

    private function addActivitySevenFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-seven.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withWeather(Weather::fromRawData(
                        Json::decode('{"latitude":51.199997,"longitude":3.199997,"generationtime_ms":1.9360780715942383,"utc_offset_seconds":7200,"timezone":"Europe/Brussels","timezone_abbreviation":"CEST","elevation":10,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relativehumidity_2m":"%","dewpoint_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","snowfall":"cm","weathercode":"wmo code","pressure_msl":"hPa","cloudcover":"%","cloudcover_low":"%","cloudcover_mid":"%","cloudcover_high":"%","et0_fao_evapotranspiration":"mm","vapor_pressure_deficit":"kPa","windspeed_10m":"km/h","windspeed_100m":"km/h","winddirection_10m":"°","winddirection_100m":"°","windgusts_10m":"km/h"},"hourly":{"time":["2023-07-29T00:00","2023-07-29T01:00","2023-07-29T02:00","2023-07-29T03:00","2023-07-29T04:00","2023-07-29T05:00","2023-07-29T06:00","2023-07-29T07:00","2023-07-29T08:00","2023-07-29T09:00","2023-07-29T10:00","2023-07-29T11:00","2023-07-29T12:00","2023-07-29T13:00","2023-07-29T14:00","2023-07-29T15:00","2023-07-29T16:00","2023-07-29T17:00","2023-07-29T18:00","2023-07-29T19:00","2023-07-29T20:00","2023-07-29T21:00","2023-07-29T22:00","2023-07-29T23:00"],"temperature_2m":[17.3,16.8,16.3,16.1,16.1,16.2,16.2,16.5,17.1,18,18.5,18.9,18.8,19.6,20.8,21.3,21.3,20.6,20.7,20.2,19.4,18.7,17.6,16.9],"relativehumidity_2m":[82,83,85,86,87,88,89,88,86,82,80,79,81,81,78,75,70,67,62,63,68,67,72,77],"dewpoint_2m":[14.3,13.9,13.8,13.8,14,14.2,14.3,14.5,14.8,14.9,15,15.1,15.5,16.2,16.8,16.8,15.7,14.3,13.3,12.9,13.2,12.6,12.6,12.7],"apparent_temperature":[16.1,15.7,15.3,15.2,15,14.8,14.7,15,15.9,16.7,17.2,17.5,17.6,18.6,20,20.4,19.5,18.3,18,17.3,17,16.2,15.2,14.5],"precipitation":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"rain":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"snowfall":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"weathercode":[1,1,1,1,3,3,2,3,51,51,51,51,51,51,51,51,51,51,0,0,1,1,1,0],"pressure_msl":[1007.8,1007.6,1007.8,1007.2,1007.2,1007.1,1007.1,1007.1,1007.1,1007.4,1007.2,1007.4,1007.5,1007.7,1007.5,1007.5,1007.4,1007.6,1007.5,1007.6,1008.1,1008.4,1008.9,1009.2],"cloudcover":[30,30,31,37,83,100,79,100,100,100,100,100,74,45,56,48,32,22,8,11,32,32,31,13],"cloudcover_low":[1,0,1,3,16,44,53,77,99,98,100,97,65,31,36,32,15,9,6,6,9,8,12,10],"cloudcover_mid":[0,0,0,7,65,88,41,59,42,67,92,93,17,23,36,31,30,23,4,9,14,6,2,2],"cloudcover_high":[98,99,100,99,97,97,21,3,22,35,34,48,18,11,7,2,2,0,0,2,52,69,63,10],"et0_fao_evapotranspiration":[0.03,0.02,0.02,0.01,0.01,0.01,0.01,0.03,0.06,0.11,0.14,0.15,0.15,0.23,0.33,0.33,0.31,0.3,0.31,0.24,0.17,0.11,0.07,0.05],"vapor_pressure_deficit":[0.35,0.32,0.28,0.25,0.23,0.22,0.21,0.22,0.27,0.37,0.42,0.46,0.4,0.43,0.54,0.63,0.75,0.8,0.92,0.87,0.73,0.7,0.56,0.45],"windspeed_10m":[17.8,15.7,15.3,15.1,16.3,18.8,19.9,20.1,19.1,20.1,20.6,21.1,20.9,21.3,23.2,24,25,25.8,26.1,26.2,23.5,22.6,21.8,21.9],"windspeed_100m":[29.9,26.9,26.3,26,27.3,29.9,30.8,30.7,29.3,28,28,30,28.5,27.8,30.7,31.9,33.4,34.9,36.3,36.6,34.7,34.9,34.6,34.7],"winddirection_10m":[234,233,221,220,221,225,228,227,226,224,223,226,238,236,237,240,247,243,246,249,245,239,236,234],"winddirection_100m":[239,240,230,228,228,229,232,230,227,225,223,226,238,236,236,240,248,244,246,249,246,242,239,237],"windgusts_10m":[27,27,27,27,28.8,30.2,31.7,32,31.3,38.5,41.4,42.8,41.4,45.4,49.3,55.1,52.9,44.6,45.4,43.9,39.2,37.1,35.6,35.3]},"daily_units":{"time":"iso8601","weathercode":"wmo code","temperature_2m_max":"°C","temperature_2m_min":"°C","temperature_2m_mean":"°C","apparent_temperature_max":"°C","apparent_temperature_min":"°C","apparent_temperature_mean":"°C","sunrise":"iso8601","sunset":"iso8601","precipitation_sum":"mm","rain_sum":"mm","snowfall_sum":"cm","precipitation_hours":"h","windspeed_10m_max":"km/h","windgusts_10m_max":"km/h","winddirection_10m_dominant":"°","shortwave_radiation_sum":"MJ/m²","et0_fao_evapotranspiration":"mm"},"daily":{"time":["2023-07-29"],"weathercode":[51],"temperature_2m_max":[21.3],"temperature_2m_min":[16.1],"temperature_2m_mean":[18.3],"apparent_temperature_max":[20.4],"apparent_temperature_min":[14.5],"apparent_temperature_mean":[16.7],"sunrise":["2023-07-29T06:07"],"sunset":["2023-07-29T21:39"],"precipitation_sum":[1.3],"rain_sum":[1.3],"snowfall_sum":[0],"precipitation_hours":[10],"windspeed_10m_max":[26.2],"windgusts_10m_max":[55.1],"winddirection_10m_dominant":[234],"shortwave_radiation_sum":[16.77],"et0_fao_evapotranspiration":[3.2]}}'),
                        SerializableDateTime::fromString('2023-09-11 21:05:38')
                    ));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var ActivityStreamRepository $activityStreamRepository */
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $heartRateStreamFor9830227182 = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('9830227182'))
            ->withStreamType(StreamType::HEART_RATE)
            ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
            ->withData(Json::decode('[177,177,177,177,178,178,178,178,178,178]'))
            ->build();
        $activityStreamRepository->add($heartRateStreamFor9830227182);

        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9830227182'),
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: self::valueDistributionFor($heartRateStreamFor9830227182),
        ));
    }

    private function addActivityEightFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-eight.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withWeather(Weather::fromRawData(
                        Json::decode('{"latitude":51.199997,"longitude":3.199997,"generationtime_ms":1.9360780715942383,"utc_offset_seconds":7200,"timezone":"Europe/Brussels","timezone_abbreviation":"CEST","elevation":10,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relativehumidity_2m":"%","dewpoint_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","snowfall":"cm","weathercode":"wmo code","pressure_msl":"hPa","cloudcover":"%","cloudcover_low":"%","cloudcover_mid":"%","cloudcover_high":"%","et0_fao_evapotranspiration":"mm","vapor_pressure_deficit":"kPa","windspeed_10m":"km/h","windspeed_100m":"km/h","winddirection_10m":"°","winddirection_100m":"°","windgusts_10m":"km/h"},"hourly":{"time":["2023-07-29T00:00","2023-07-29T01:00","2023-07-29T02:00","2023-07-29T03:00","2023-07-29T04:00","2023-07-29T05:00","2023-07-29T06:00","2023-07-29T07:00","2023-07-29T08:00","2023-07-29T09:00","2023-07-29T10:00","2023-07-29T11:00","2023-07-29T12:00","2023-07-29T13:00","2023-07-29T14:00","2023-07-29T15:00","2023-07-29T16:00","2023-07-29T17:00","2023-07-29T18:00","2023-07-29T19:00","2023-07-29T20:00","2023-07-29T21:00","2023-07-29T22:00","2023-07-29T23:00"],"temperature_2m":[17.3,16.8,16.3,16.1,16.1,16.2,16.2,16.5,17.1,18,18.5,18.9,18.8,19.6,20.8,21.3,21.3,20.6,20.7,20.2,19.4,18.7,17.6,16.9],"relativehumidity_2m":[82,83,85,86,87,88,89,88,86,82,80,79,81,81,78,75,70,67,62,63,68,67,72,77],"dewpoint_2m":[14.3,13.9,13.8,13.8,14,14.2,14.3,14.5,14.8,14.9,15,15.1,15.5,16.2,16.8,16.8,15.7,14.3,13.3,12.9,13.2,12.6,12.6,12.7],"apparent_temperature":[16.1,15.7,15.3,15.2,15,14.8,14.7,15,15.9,16.7,17.2,17.5,17.6,18.6,20,20.4,19.5,18.3,18,17.3,17,16.2,15.2,14.5],"precipitation":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"rain":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"snowfall":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"weathercode":[1,1,1,1,3,3,2,3,51,51,51,51,51,51,51,51,51,51,0,0,1,1,1,0],"pressure_msl":[1007.8,1007.6,1007.8,1007.2,1007.2,1007.1,1007.1,1007.1,1007.1,1007.4,1007.2,1007.4,1007.5,1007.7,1007.5,1007.5,1007.4,1007.6,1007.5,1007.6,1008.1,1008.4,1008.9,1009.2],"cloudcover":[30,30,31,37,83,100,79,100,100,100,100,100,74,45,56,48,32,22,8,11,32,32,31,13],"cloudcover_low":[1,0,1,3,16,44,53,77,99,98,100,97,65,31,36,32,15,9,6,6,9,8,12,10],"cloudcover_mid":[0,0,0,7,65,88,41,59,42,67,92,93,17,23,36,31,30,23,4,9,14,6,2,2],"cloudcover_high":[98,99,100,99,97,97,21,3,22,35,34,48,18,11,7,2,2,0,0,2,52,69,63,10],"et0_fao_evapotranspiration":[0.03,0.02,0.02,0.01,0.01,0.01,0.01,0.03,0.06,0.11,0.14,0.15,0.15,0.23,0.33,0.33,0.31,0.3,0.31,0.24,0.17,0.11,0.07,0.05],"vapor_pressure_deficit":[0.35,0.32,0.28,0.25,0.23,0.22,0.21,0.22,0.27,0.37,0.42,0.46,0.4,0.43,0.54,0.63,0.75,0.8,0.92,0.87,0.73,0.7,0.56,0.45],"windspeed_10m":[17.8,15.7,15.3,15.1,16.3,18.8,19.9,20.1,19.1,20.1,20.6,21.1,20.9,21.3,23.2,24,25,25.8,26.1,26.2,23.5,22.6,21.8,21.9],"windspeed_100m":[29.9,26.9,26.3,26,27.3,29.9,30.8,30.7,29.3,28,28,30,28.5,27.8,30.7,31.9,33.4,34.9,36.3,36.6,34.7,34.9,34.6,34.7],"winddirection_10m":[234,233,221,220,221,225,228,227,226,224,223,226,238,236,237,240,247,243,246,249,245,239,236,234],"winddirection_100m":[239,240,230,228,228,229,232,230,227,225,223,226,238,236,236,240,248,244,246,249,246,242,239,237],"windgusts_10m":[27,27,27,27,28.8,30.2,31.7,32,31.3,38.5,41.4,42.8,41.4,45.4,49.3,55.1,52.9,44.6,45.4,43.9,39.2,37.1,35.6,35.3]},"daily_units":{"time":"iso8601","weathercode":"wmo code","temperature_2m_max":"°C","temperature_2m_min":"°C","temperature_2m_mean":"°C","apparent_temperature_max":"°C","apparent_temperature_min":"°C","apparent_temperature_mean":"°C","sunrise":"iso8601","sunset":"iso8601","precipitation_sum":"mm","rain_sum":"mm","snowfall_sum":"cm","precipitation_hours":"h","windspeed_10m_max":"km/h","windgusts_10m_max":"km/h","winddirection_10m_dominant":"°","shortwave_radiation_sum":"MJ/m²","et0_fao_evapotranspiration":"mm"},"daily":{"time":["2023-07-29"],"weathercode":[51],"temperature_2m_max":[21.3],"temperature_2m_min":[16.1],"temperature_2m_mean":[18.3],"apparent_temperature_max":[20.4],"apparent_temperature_min":[14.5],"apparent_temperature_mean":[16.7],"sunrise":["2023-07-29T06:07"],"sunset":["2023-07-29T21:39"],"precipitation_sum":[1.3],"rain_sum":[1.3],"snowfall_sum":[0],"precipitation_hours":[10],"windspeed_10m_max":[26.2],"windgusts_10m_max":[55.1],"winddirection_10m_dominant":[234],"shortwave_radiation_sum":[16.77],"et0_fao_evapotranspiration":[3.2]}}'),
                        SerializableDateTime::fromString('2023-09-12 21:05:38')
                    ));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));
    }

    private function addActivityNineFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-nine.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withLocalImagePaths(['files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg']);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var ActivityStreamRepository $activityStreamRepository */
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);

        $heartRateStreamFor8756441741 = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('8756441741'))
            ->withStreamType(StreamType::HEART_RATE)
            ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
            ->withData(Json::decode('[301,177,177,177,178,178,178,178,178,178]'))
            ->build();
        $activityStreamRepository->add($heartRateStreamFor8756441741);

        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('8756441741'),
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: self::valueDistributionFor($heartRateStreamFor8756441741),
        ));
    }

    private function addActivityTenFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-ten.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withLocalImagePaths(['files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg']);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var ActivityStreamRepository $activityStreamRepository */
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('87756441741'))
                ->withStreamType(StreamType::WATTS)
                ->withCreatedOn(SerializableDateTime::fromString('2020-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/watts.json')))
                ->build()
        );

        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('87756441741'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [-1 => 100, 0 => 100],
        ));
    }

    private function addActivityElevenFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-eleven.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withLocalImagePaths(['files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg']);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var ActivityStreamRepository $activityStreamRepository */
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('87726441741'))
                ->withStreamType(StreamType::WATTS)
                ->withCreatedOn(SerializableDateTime::fromString('2020-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/watts.json')))
                ->build()
        );

        $activityStreamMetricRepository = $this->getContainer()->get(ActivityStreamMetricRepository::class);
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('87726441741'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: Json::decode('{"1":540,"5":493,"10":460,"15":442,"30":412,"45":373,"60":352,"120":293,"180":273,"240":282,"300":273,"390":262,"480":256,"720":246,"960":239,"1200":239,"1800":225,"2400":222,"3000":222,"3600":218}'),
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('87726441741'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [1 => 2, 2 => 1, 6 => 1],
        ));
    }

    private function addActivityTwelveFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-twelve.json'));
        $activity = Activity::createFromRawStravaData($rawData)
                    ->withLocalImagePaths(['files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg', 'files/activities/aea2d7b4-53a7-11ee-a5e1-9a34a3268d74.jpg']);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var ActivityStreamRepository $activityStreamRepository */
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $heartRateStreamFor45326441741 = ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('45326441741'))
            ->withStreamType(StreamType::HEART_RATE)
            ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
            ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/activity-twelve.json')))
            ->build();
        $activityStreamRepository->add($heartRateStreamFor45326441741);
        $activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('45326441741'))
                ->withStreamType(StreamType::VELOCITY)
                ->withCreatedOn(SerializableDateTime::fromString('2023-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/activity-twelve.json')))
                ->build()
        );

        $activityStreamMetricRepository = $this->getContainer()->get(ActivityStreamMetricRepository::class);
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('45326441741'),
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: self::valueDistributionFor($heartRateStreamFor45326441741),
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('45326441741'),
            streamType: StreamType::VELOCITY,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [1 => 2, 2 => 5, 6 => 8],
        ));

        /** @var ActivitySplitRepository $activitySplitRepository */
        $activitySplitRepository = $this->getContainer()->get(ActivitySplitRepository::class);
        $activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('45326441741'))
                ->build()
        );

        /** @var ActivityLapRepository $activityLapRepository */
        $activityLapRepository = $this->getContainer()->get(ActivityLapRepository::class);
        $activityLapRepository->add(
            ActivityLapBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('45326441741'))
                ->build()
        );
    }

    private function addActivityThirteenFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-thirteen.json'));
        $activity = Activity::createFromRawStravaData($rawData);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));
    }

    private function addActivityFourteenFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-fourteen.json'));
        $activity = Activity::createFromRawStravaData($rawData);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $rawData
        ));

        /** @var ActivityStreamRepository $activityStreamRepository */
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('9756441709'))
                ->withStreamType(StreamType::WATTS)
                ->withCreatedOn(SerializableDateTime::fromString('2020-09-27 19:48:21'))
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/streams/watts.json')))
                ->build()
        );

        $activityStreamMetricRepository = $this->getContainer()->get(ActivityStreamMetricRepository::class);
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441709'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: Json::decode('{"1":540,"5":493,"10":460,"15":442,"30":412,"45":373,"60":352,"120":293,"180":273,"240":282,"300":273,"390":262,"480":256,"720":246,"960":239,"1200":239,"1800":225,"2400":222,"3000":222,"3600":218}'),
        ));
        $activityStreamMetricRepository->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441709'),
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [1 => 2, 2 => 5, 6 => 8],
        ));
    }

    private function addActivityFifteenFixtures(): void
    {
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);

        $rawData = Json::decode((string) file_get_contents(__DIR__.'/fixtures/strava/activities/activity-fifteen.json'));
        $activity = Activity::createFromRawStravaData($rawData)
            ->withAverageSpeed(KmPerHour::from(12));
        $activityRepository->add(ActivityWithRawData::fromState(
            $activity,
            $rawData
        ));

        /** @var ActivitySplitRepository $activitySplitRepository */
        $activitySplitRepository = $this->getContainer()->get(ActivitySplitRepository::class);
        $activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withSplitNumber(1)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withDistanceInMeter(1000)
                ->withGapPace(SecPerKm::from(300))
                ->build()
        );
        $activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withSplitNumber(2)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withDistanceInMeter(1000)
                ->withGapPace(SecPerKm::from(310))
                ->build()
        );
    }

    public function buildThemeConfig(): array
    {
        return [
            'sportType' => [
                'Ride' => '#5470c6',
                'MountainBikeRide' => '#91cc75',
                'GravelRide' => '#fac858',
                'EBikeRide' => '#ee6666',
                'EMountainBikeRide' => '#73c0de',
                'VirtualRide' => '#3ba272',
                'Velomobile' => '#fc8452',
                'Run' => '#9a60b4',
                'TrailRun' => '#ea7ccc',
                'VirtualRun' => '#5470c6',
                'Walk' => '#91cc75',
                'Hike' => '#fac858',
                'Canoeing' => '#ee6666',
                'Kayaking' => '#73c0de',
                'Kitesurf' => '#3ba272',
                'Rowing' => '#fc8452',
                'StandUpPaddling' => '#9a60b4',
                'Surfing' => '#ea7ccc',
                'Swim' => '#5470c6',
                'Windsurf' => '#91cc75',
                'BackcountrySki' => '#fac858',
                'AlpineSki' => '#ee6666',
                'NordicSki' => '#73c0de',
                'IceSkate' => '#3ba272',
                'Snowboard' => '#fc8452',
                'Snowshoe' => '#9a60b4',
                'Skateboard' => '#ea7ccc',
                'InlineSkate' => '#5470c6',
                'RollerSki' => '#91cc75',
                'Badminton' => '#fac858',
                'Pickleball' => '#ee6666',
                'Racquetball' => '#73c0de',
                'Squash' => '#3ba272',
                'TableTennis' => '#fc8452',
                'Tennis' => '#9a60b4',
                'Crossfit' => '#ea7ccc',
                'WeightTraining' => '#5470c6',
                'Workout' => '#91cc75',
                'StairStepper' => '#fac858',
                'VirtualRow' => '#ee6666',
                'HighIntensityIntervalTraining' => '#73c0de',
                'Elliptical' => '#3ba272',
                'Pilates' => '#fc8452',
                'Yoga' => '#9a60b4',
                'Golf' => '#ea7ccc',
                'RockClimbing' => '#5470c6',
                'Sail' => '#91cc75',
                'Soccer' => '#fac858',
                'Handcycle' => '#ee6666',
                'Wheelchair' => '#73c0de',
            ],
            'gear' => [
                'gear-b12659861' => '#5470c6',
                'gear-b12659862' => '#91cc75',
                'gear-b12659562' => '#ea7ccc',
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function valueDistributionFor(ActivityStream $activityStream): array
    {
        $valueDistribution = array_count_values(array_filter(
            $activityStream->getData(),
            fn (mixed $item): bool => !is_null($item)
        ));
        ksort($valueDistribution);

        return $valueDistribution;
    }
}
