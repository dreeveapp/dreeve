<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityRepository;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class DbalActivityRepositoryTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;

    public function testFind(): void
    {
        $activityOne = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityOne,
            ['raw' => 'data']
        ));

        $this->assertEquals(
            $activityOne,
            $this->activityRepository->find($activityOne->getId())
        );

        $activityTwo = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(2))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityTwo,
            ['raw' => 'data']
        ));

        $this->assertEquals(
            $activityTwo,
            $this->activityRepository->find($activityTwo->getId())
        );
    }

    public function testFindWithEmptyNameItShouldFallBackToGeneratedName(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->withSportType(SportType::RIDE)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activity,
            ['raw' => 'data']
        ));

        // Legacy activities can contain empty titles...
        $this->getConnection()->executeStatement(
            'UPDATE Activity SET name = :name WHERE activityId = :activityId',
            [
                'name' => '   ',
                'activityId' => $activity->getId(),
            ]
        );

        $persisted = $this->activityRepository->find($activity->getId());

        $this->assertEquals(
            'Afternoon Ride',
            $persisted->getName()
        );
        $this->assertEquals(
            ActivityName::from(
                SerializableDateTime::fromString('2023-10-10 14:00:34'),
                SportType::RIDE
            ),
            ActivityName::fromString($persisted->getName())
        );
    }

    public function testItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-1" not found'));
        $this->activityRepository->find(ActivityId::fromUnprefixed(1));
    }

    public function testFindWithRawDataItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-1" not found'));
        $this->activityRepository->findWithRawData(ActivityId::fromUnprefixed(1));
    }

    public function testAddAndFindWithRawData(): void
    {
        $activityWithRawData = ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->build(),
            ['raw' => 'data']
        );

        $this->activityRepository->add($activityWithRawData);

        $persisted = $this->activityRepository->findWithRawData($activityWithRawData->getActivity()->getId());
        $this->assertEquals(
            $activityWithRawData,
            $persisted,
        );
    }

    public function testAddAndFindWithRawDataFromRawData(): void
    {
        $rawData = Json::decode('{"resource_state":3,"athlete":{"id":62214940,"resource_state":1},"name":"Morning Ride","distance":12415.1,"moving_time":1916,"elapsed_time":15265,"total_elevation_gain":15.1,"type":"Run","sport_type":"Run","workout_type":10,"id":9542782314,"start_date":"2023-07-28T09:34:03Z","start_date_local":"2023-07-28T09:34:03Z","timezone":"(GMT+01:00) Europe/Brussels","utc_offset":7200,"location_city":null,"location_state":null,"location_country":null,"achievement_count":1,"kudos_count":0,"comment_count":0,"athlete_count":1,"photo_count":0,"trainer":false,"commute":false,"manual":false,"private":false,"visibility":"everyone","flagged":false,"gear_id":"b12659861","start_latlng":[51.19354132,3.24],"end_latlng":[51.19,3.24],"average_speed":6.48,"max_speed":10.38,"average_watts":92.3,"kilojoules":176.9,"device_watts":false,"has_heartrate":true,"average_heartrate":128.9,"max_heartrate":161,"heartrate_opt_out":false,"display_hide_heartrate_option":true,"elev_high":11.6,"elev_low":2.8,"upload_id":10233168035,"upload_id_str":"10233168035","external_id":"GOTOES_5812292553058048.tcx","from_accepted_tag":false,"pr_count":0,"total_photo_count":0,"has_kudoed":false,"description":"","calories":293,"perceived_exertion":null,"prefer_perceived_exertion":false,"segment_efforts":[{"id":3120339676122301400,"resource_state":2,"name":"Steenbrugge-Moerbrugge","activity":{"id":9542782814,"resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":323,"moving_time":323,"start_date":"2023-07-29T07:37:23Z","start_date_local":"2023-07-29T09:37:23Z","distance":2483.1,"start_index":0,"end_index":268,"device_watts":false,"average_watts":117.4,"average_heartrate":139.4,"max_heartrate":147,"segment":{"id":25228950,"resource_state":2,"name":"Steenbrugge-Moerbrugge","activity_type":"Ride","distance":2483.1,"average_grade":0,"maximum_grade":0.9,"elevation_high":6.8,"elevation_low":5.2,"start_latlng":[51.177806,3.234499],"end_latlng":[51.161955,3.257633],"elevation_profile":null,"climb_category":0,"city":"Oostkamp","state":"Vlaanderen","country":"Belgium","private":false,"hazardous":false,"starred":false},"pr_rank":null,"achievements":[],"hidden":false},{"id":3120339676123355000,"resource_state":2,"name":"draaien en draaien onder de sporen","activity":{"id":9542782814,"resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":52,"moving_time":42,"start_date":"2023-07-29T07:47:08Z","start_date_local":"2023-07-29T09:47:08Z","distance":308,"start_index":479,"end_index":531,"device_watts":false,"average_watts":46.9,"average_heartrate":142.7,"max_heartrate":148,"segment":{"id":25659413,"resource_state":2,"name":"draaien en draaien onder de sporen","activity_type":"Ride","distance":308,"average_grade":0.1,"maximum_grade":1.3,"elevation_high":7.7,"elevation_low":7.2,"start_latlng":[51.152313,3.26288],"end_latlng":[51.151644,3.263161],"elevation_profile":null,"climb_category":0,"city":"Oostkamp","state":"Vlaanderen","country":"Belgium","private":false,"hazardous":false,"starred":false},"pr_rank":3,"achievements":[{"type_id":3,"type":"pr","rank":3}],"hidden":false},{"id":3120339676121533400,"resource_state":2,"name":"Oostkamp(Stationstraat)-Steenbrugge(St-Michielsestraat)","activity":{"id":9542782814,"resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":3007,"moving_time":632,"start_date":"2023-07-29T10:55:09Z","start_date_local":"2023-07-29T12:55:09Z","distance":3512.4,"start_index":841,"end_index":1374,"device_watts":false,"average_watts":17.9,"average_heartrate":122.8,"max_heartrate":154,"segment":{"id":6785309,"resource_state":2,"name":"Oostkamp(Stationstraat)-Steenbrugge(St-Michielsestraat)","activity_type":"Ride","distance":3512.4,"average_grade":-0.2,"maximum_grade":2.6,"elevation_high":10,"elevation_low":1.6,"start_latlng":[51.153382,3.251588],"end_latlng":[51.177281,3.233777],"elevation_profile":null,"climb_category":0,"city":"Oostkamp","state":"Flanders","country":"Belgium","private":false,"hazardous":false,"starred":false},"pr_rank":null,"achievements":[],"hidden":false},{"id":3120339676125132000,"resource_state":2,"name":"Brug Oostkamp - Steenbrugge L50A","activity":{"id":9542782814,"resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":32,"moving_time":32,"start_date":"2023-07-29T11:39:00Z","start_date_local":"2023-07-29T13:39:00Z","distance":241.7,"start_index":1309,"end_index":1341,"device_watts":false,"average_watts":235.1,"average_heartrate":152.5,"max_heartrate":154,"segment":{"id":10368696,"resource_state":2,"name":"Brug Oostkamp - Steenbrugge L50A","activity_type":"Ride","distance":241.7,"average_grade":0.6,"maximum_grade":2.1,"elevation_high":3.9,"elevation_low":2.2,"start_latlng":[51.168744,3.23388],"end_latlng":[51.170908,3.233762],"elevation_profile":null,"climb_category":0,"city":"Oostkamp","state":"Vlaanderen","country":"Belgium","private":false,"hazardous":false,"starred":false},"pr_rank":null,"achievements":[],"hidden":false}],"splits_metric":[{"distance":1004.5,"elapsed_time":162,"elevation_difference":1.6,"moving_time":162,"split":1,"average_speed":6.2,"average_grade_adjusted_speed":null,"average_heartrate":114.06172839506173,"pace_zone":0},{"distance":999.9,"elapsed_time":139,"elevation_difference":-0.7,"moving_time":139,"split":2,"average_speed":7.19,"average_grade_adjusted_speed":null,"average_heartrate":135.44604316546761,"pace_zone":0},{"distance":996.8,"elapsed_time":130,"elevation_difference":0.4,"moving_time":130,"split":3,"average_speed":7.67,"average_grade_adjusted_speed":null,"average_heartrate":138.5,"pace_zone":0},{"distance":1003.6,"elapsed_time":131,"elevation_difference":0,"moving_time":131,"split":4,"average_speed":7.66,"average_grade_adjusted_speed":null,"average_heartrate":143.29770992366412,"pace_zone":0},{"distance":996.8,"elapsed_time":199,"elevation_difference":1.3,"moving_time":147,"split":5,"average_speed":6.78,"average_grade_adjusted_speed":null,"average_heartrate":141.87074829931973,"pace_zone":0},{"distance":1002.5,"elapsed_time":11095,"elevation_difference":-0.3,"moving_time":168,"split":6,"average_speed":5.97,"average_grade_adjusted_speed":null,"average_heartrate":131.67088607594937,"pace_zone":0},{"distance":999.9,"elapsed_time":174,"elevation_difference":-1.7,"moving_time":174,"split":7,"average_speed":5.75,"average_grade_adjusted_speed":null,"average_heartrate":113.39655172413794,"pace_zone":0},{"distance":999.1,"elapsed_time":186,"elevation_difference":0.4,"moving_time":186,"split":8,"average_speed":5.37,"average_grade_adjusted_speed":null,"average_heartrate":115.67204301075269,"pace_zone":0},{"distance":997.4,"elapsed_time":2386,"elevation_difference":0.1,"moving_time":205,"split":9,"average_speed":4.87,"average_grade_adjusted_speed":null,"average_heartrate":111.04878048780488,"pace_zone":0},{"distance":1005.3,"elapsed_time":132,"elevation_difference":6,"moving_time":132,"split":10,"average_speed":7.62,"average_grade_adjusted_speed":null,"average_heartrate":132.40151515151516,"pace_zone":0},{"distance":996.2,"elapsed_time":332,"elevation_difference":-7.3,"moving_time":143,"split":11,"average_speed":6.97,"average_grade_adjusted_speed":null,"average_heartrate":134.33333333333334,"pace_zone":0},{"distance":1006.1,"elapsed_time":135,"elevation_difference":-0.2,"moving_time":135,"split":12,"average_speed":7.45,"average_grade_adjusted_speed":null,"average_heartrate":143.56296296296296,"pace_zone":0},{"distance":407,"elapsed_time":64,"elevation_difference":-0.4,"moving_time":64,"split":13,"average_speed":6.36,"average_grade_adjusted_speed":null,"average_heartrate":154.015625,"pace_zone":0}],"splits_standard":[{"distance":1612.2,"elapsed_time":251,"elevation_difference":0.6,"moving_time":251,"split":1,"average_speed":6.42,"average_grade_adjusted_speed":null,"average_heartrate":120.96414342629483,"pace_zone":0},{"distance":1611.4,"elapsed_time":208,"elevation_difference":1,"moving_time":208,"split":2,"average_speed":7.75,"average_grade_adjusted_speed":null,"average_heartrate":139.0721153846154,"pace_zone":0},{"distance":1610.3,"elapsed_time":279,"elevation_difference":1.1,"moving_time":227,"split":3,"average_speed":7.09,"average_grade_adjusted_speed":null,"average_heartrate":141.8281938325991,"pace_zone":0},{"distance":1607.3,"elapsed_time":11196,"elevation_difference":0.1,"moving_time":269,"split":4,"average_speed":5.98,"average_grade_adjusted_speed":null,"average_heartrate":125.7876447876448,"pace_zone":0},{"distance":1608.2,"elapsed_time":291,"elevation_difference":-1.7,"moving_time":291,"split":5,"average_speed":5.53,"average_grade_adjusted_speed":null,"average_heartrate":116.56013745704468,"pace_zone":0},{"distance":1609.4,"elapsed_time":2463,"elevation_difference":0.5,"moving_time":282,"split":6,"average_speed":5.71,"average_grade_adjusted_speed":null,"average_heartrate":114.49645390070921,"pace_zone":0},{"distance":1612.8,"elapsed_time":412,"elevation_difference":-0.5,"moving_time":223,"split":7,"average_speed":7.23,"average_grade_adjusted_speed":null,"average_heartrate":139.06422018348624,"pace_zone":0},{"distance":1143.5,"elapsed_time":165,"elevation_difference":-1.9,"moving_time":165,"split":8,"average_speed":6.93,"average_grade_adjusted_speed":null,"average_heartrate":147.9939393939394,"pace_zone":0}],"laps":[{"id":32595763857,"resource_state":2,"name":"Lap 1","activity":{"id":9542782814,"resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":865,"moving_time":835,"start_date":"2023-07-29T07:34:03Z","start_date_local":"2023-07-29T09:34:03Z","distance":5815.6,"start_index":0,"end_index":581,"total_elevation_gain":3,"average_speed":6.96,"max_speed":9.72,"device_watts":false,"average_watts":103.4,"average_heartrate":135.4,"max_heartrate":151,"lap_index":1,"split":1},{"id":32595763868,"resource_state":2,"name":"Lap 2","activity":{"id":9542782814,"resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":601,"moving_time":589,"start_date":"2023-07-29T10:50:50Z","start_date_local":"2023-07-29T12:50:50Z","distance":3066.3,"start_index":582,"end_index":1182,"total_elevation_gain":2.7,"average_speed":5.21,"max_speed":6.7,"device_watts":false,"average_watts":55.3,"average_heartrate":112.9,"max_heartrate":149,"lap_index":2,"split":2},{"id":32595763877,"resource_state":2,"name":"Lap 3","activity":{"id":9542782814,"resource_state":1},"athlete":{"id":62214940,"resource_state":1},"elapsed_time":503,"moving_time":492,"start_date":"2023-07-29T11:36:54Z","start_date_local":"2023-07-29T13:36:54Z","distance":3543.6,"start_index":1183,"end_index":1374,"total_elevation_gain":9.4,"average_speed":7.2,"max_speed":10.38,"device_watts":false,"average_watts":118.2,"average_heartrate":137.2,"max_heartrate":161,"lap_index":3,"split":3}],"gear":{"id":"b12659861","primary":false,"name":"Retro Race Bike","nickname":"Retro Race Bike","resource_state":2,"retired":false,"distance":721212,"converted_distance":721.2},"photos":{"primary":null,"count":0},"stats_visibility":[{"type":"heart_rate","visibility":"everyone"},{"type":"pace","visibility":"everyone"},{"type":"power","visibility":"everyone"},{"type":"speed","visibility":"everyone"},{"type":"calories","visibility":"everyone"}],"hide_from_home":true,"device_name":"Polar Vantage M","embed_token":"5309068c78d63cd51298b901597550df99181267","private_note":"","available_zones":[],"athlete_weight":69,"start_date_timestamp":1690616043,"_id":115,"weather":{"latitude":51.199997,"longitude":3.199997,"generationtime_ms":1.9360780715942383,"utc_offset_seconds":7200,"timezone":"Europe/Brussels","timezone_abbreviation":"CEST","elevation":10,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relativehumidity_2m":"%","dewpoint_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","snowfall":"cm","weathercode":"wmo code","pressure_msl":"hPa","cloudcover":"%","cloudcover_low":"%","cloudcover_mid":"%","cloudcover_high":"%","et0_fao_evapotranspiration":"mm","vapor_pressure_deficit":"kPa","windspeed_10m":"km/h","windspeed_100m":"km/h","winddirection_10m":"°","winddirection_100m":"°","windgusts_10m":"km/h"},"hourly":{"time":["2023-07-29T00:00","2023-07-29T01:00","2023-07-29T02:00","2023-07-29T03:00","2023-07-29T04:00","2023-07-29T05:00","2023-07-29T06:00","2023-07-29T07:00","2023-07-29T08:00","2023-07-29T09:00","2023-07-29T10:00","2023-07-29T11:00","2023-07-29T12:00","2023-07-29T13:00","2023-07-29T14:00","2023-07-29T15:00","2023-07-29T16:00","2023-07-29T17:00","2023-07-29T18:00","2023-07-29T19:00","2023-07-29T20:00","2023-07-29T21:00","2023-07-29T22:00","2023-07-29T23:00"],"temperature_2m":[17.3,16.8,16.3,16.1,16.1,16.2,16.2,16.5,17.1,18,18.5,18.9,18.8,19.6,20.8,21.3,21.3,20.6,20.7,20.2,19.4,18.7,17.6,16.9],"relativehumidity_2m":[82,83,85,86,87,88,89,88,86,82,80,79,81,81,78,75,70,67,62,63,68,67,72,77],"dewpoint_2m":[14.3,13.9,13.8,13.8,14,14.2,14.3,14.5,14.8,14.9,15,15.1,15.5,16.2,16.8,16.8,15.7,14.3,13.3,12.9,13.2,12.6,12.6,12.7],"apparent_temperature":[16.1,15.7,15.3,15.2,15,14.8,14.7,15,15.9,16.7,17.2,17.5,17.6,18.6,20,20.4,19.5,18.3,18,17.3,17,16.2,15.2,14.5],"precipitation":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"rain":[0,0,0,0,0,0,0,0,0.2,0.1,0.1,0.1,0.3,0.1,0.1,0.1,0.1,0.1,0,0,0,0,0,0],"snowfall":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"weathercode":[1,1,1,1,3,3,2,3,51,51,51,51,51,51,51,51,51,51,0,0,1,1,1,0],"pressure_msl":[1007.8,1007.6,1007.8,1007.2,1007.2,1007.1,1007.1,1007.1,1007.1,1007.4,1007.2,1007.4,1007.5,1007.7,1007.5,1007.5,1007.4,1007.6,1007.5,1007.6,1008.1,1008.4,1008.9,1009.2],"cloudcover":[30,30,31,37,83,100,79,100,100,100,100,100,74,45,56,48,32,22,8,11,32,32,31,13],"cloudcover_low":[1,0,1,3,16,44,53,77,99,98,100,97,65,31,36,32,15,9,6,6,9,8,12,10],"cloudcover_mid":[0,0,0,7,65,88,41,59,42,67,92,93,17,23,36,31,30,23,4,9,14,6,2,2],"cloudcover_high":[98,99,100,99,97,97,21,3,22,35,34,48,18,11,7,2,2,0,0,2,52,69,63,10],"et0_fao_evapotranspiration":[0.03,0.02,0.02,0.01,0.01,0.01,0.01,0.03,0.06,0.11,0.14,0.15,0.15,0.23,0.33,0.33,0.31,0.3,0.31,0.24,0.17,0.11,0.07,0.05],"vapor_pressure_deficit":[0.35,0.32,0.28,0.25,0.23,0.22,0.21,0.22,0.27,0.37,0.42,0.46,0.4,0.43,0.54,0.63,0.75,0.8,0.92,0.87,0.73,0.7,0.56,0.45],"windspeed_10m":[17.8,15.7,15.3,15.1,16.3,18.8,19.9,20.1,19.1,20.1,20.6,21.1,20.9,21.3,23.2,24,25,25.8,26.1,26.2,23.5,22.6,21.8,21.9],"windspeed_100m":[29.9,26.9,26.3,26,27.3,29.9,30.8,30.7,29.3,28,28,30,28.5,27.8,30.7,31.9,33.4,34.9,36.3,36.6,34.7,34.9,34.6,34.7],"winddirection_10m":[234,233,221,220,221,225,228,227,226,224,223,226,238,236,237,240,247,243,246,249,245,239,236,234],"winddirection_100m":[239,240,230,228,228,229,232,230,227,225,223,226,238,236,236,240,248,244,246,249,246,242,239,237],"windgusts_10m":[27,27,27,27,28.8,30.2,31.7,32,31.3,38.5,41.4,42.8,41.4,45.4,49.3,55.1,52.9,44.6,45.4,43.9,39.2,37.1,35.6,35.3]},"daily_units":{"time":"iso8601","weathercode":"wmo code","temperature_2m_max":"°C","temperature_2m_min":"°C","temperature_2m_mean":"°C","apparent_temperature_max":"°C","apparent_temperature_min":"°C","apparent_temperature_mean":"°C","sunrise":"iso8601","sunset":"iso8601","precipitation_sum":"mm","rain_sum":"mm","snowfall_sum":"cm","precipitation_hours":"h","windspeed_10m_max":"km/h","windgusts_10m_max":"km/h","winddirection_10m_dominant":"°","shortwave_radiation_sum":"MJ/m²","et0_fao_evapotranspiration":"mm"},"daily":{"time":["2023-07-29"],"weathercode":[51],"temperature_2m_max":[21.3],"temperature_2m_min":[16.1],"temperature_2m_mean":[18.3],"apparent_temperature_max":[20.4],"apparent_temperature_min":[14.5],"apparent_temperature_mean":[16.7],"sunrise":["2023-07-29T06:07"],"sunset":["2023-07-29T21:39"],"precipitation_sum":[1.3],"rain_sum":[1.3],"snowfall_sum":[0],"precipitation_hours":[10],"windspeed_10m_max":[26.2],"windgusts_10m_max":[55.1],"winddirection_10m_dominant":[234],"shortwave_radiation_sum":[16.77],"et0_fao_evapotranspiration":[3.2]}}}');

        $activityWithRawData = ActivityWithRawData::fromState(
            Activity::createFromRawStravaData($rawData),
            $rawData
        );

        $this->activityRepository->add($activityWithRawData);

        $persisted = $this->activityRepository->findWithRawData($activityWithRawData->getActivity()->getId());
        $this->assertEquals(
            $activityWithRawData,
            $persisted,
        );
    }

    public function testUpdate(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $activityWithRawData = ActivityWithRawData::fromState(
            $activity,
            ['raw' => 'data']
        );

        $this->activityRepository->add($activityWithRawData);

        $activity = $activity
            ->withName(ActivityName::fromString('Updated name'))
            ->withDescription('Updated description')
            ->withDeviceName('Updated device')
            ->withSportType(SportType::BADMINTON)
            ->withDistance(Kilometer::from(9.99))
            ->withAverageSpeed(MetersPerSecond::from(19.99)->toKmPerHour())
            ->withMaxSpeed(MetersPerSecond::from(99.99)->toKmPerHour())
            ->withMovingTimeInSeconds(999)
            ->withElevation(Meter::from(9999))
            ->withPolyline('updated polyline')
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('20'),
                longitude: Longitude::fromString('20'),
            ))
            ->withGear(GearId::fromUnprefixed('updated'))
            ->withRouteGeography(RouteGeography::create(['state' => 'updated location']))
            ->withCommute(true);

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: ['updated' => 'lol']
        ));

        $persisted = $this->activityRepository->findWithRawData($activity->getId());
        $persistedActivity = $persisted->getActivity();
        $this->assertEquals(
            ['updated' => 'lol'],
            $persisted->getRawData()
        );
        $this->assertEquals(
            'Updated name',
            $persistedActivity->getName()
        );
        $this->assertEquals(
            'Updated description',
            $persistedActivity->getDescription()
        );
        $this->assertEquals(
            'Updated device',
            $persistedActivity->getDeviceName()
        );
        $this->assertEquals(
            Kilometer::from(9.99),
            $persistedActivity->getDistance()
        );
        $this->assertEquals(
            MetersPerSecond::from(19.99)->toKmPerHour(),
            $persistedActivity->getAverageSpeed()
        );
        $this->assertEquals(
            MetersPerSecond::from(99.99)->toKmPerHour(),
            $persistedActivity->getMaxSpeed()
        );
        $this->assertEquals(
            999,
            $persistedActivity->getMovingTimeInSeconds()
        );
        $this->assertEquals(
            Meter::from(9999),
            $persistedActivity->getElevation()
        );
        $this->assertEquals(
            'updated polyline',
            (string) $persistedActivity->getEncodedPolyline()
        );
        $this->assertEquals(
            GearId::fromUnprefixed('updated'),
            $persistedActivity->getGearId()
        );
        $this->assertEquals(
            RouteGeography::create(['state' => 'updated location']),
            $persistedActivity->getRouteGeography()
        );
        $this->assertTrue($persistedActivity->isCommute());
        $this->assertEquals(
            Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('20'),
                longitude: Longitude::fromString('20'),
            ),
            $persistedActivity->getStartingCoordinate(),
        );
        $this->assertEquals(
            SportType::BADMINTON,
            $persistedActivity->getSportType()
        );
    }

    public function testActivityNeedsStreamImport(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $activityWithRawData = ActivityWithRawData::fromState(
            $activity,
            ['raw' => 'data']
        );

        $this->activityRepository->add($activityWithRawData);

        $this->assertTrue(
            $this->activityRepository->activityNeedsStreamImport($activity->getId())
        );
        $this->assertFalse(
            $this->activityRepository->activityNeedsStreamImport(ActivityId::fromUnprefixed('test'))
        );

        $this->activityRepository->markActivityStreamsAsImported($activity->getId());
        $this->assertFalse(
            $this->activityRepository->activityNeedsStreamImport($activity->getId())
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = new DbalActivityRepository(
            $this->getConnection(),
        );
    }
}
