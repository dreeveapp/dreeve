<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityTokenProvider;
use App\Domain\Activity\WorkoutType;
use App\Domain\Settings\AppearanceSettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Tokenizer\Token;
use App\Infrastructure\Tokenizer\TokenizerContext;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Measurement\Velocity\KmPerHour;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityTokenProviderTest extends TestCase
{
    public function testGetPrefix(): void
    {
        $this->assertSame('activity', $this->buildProvider(UnitSystem::METRIC)->getPrefix());
    }

    public function testGetTokenDefinitions(): void
    {
        foreach ($this->buildProvider(UnitSystem::METRIC)->getTokenDefinitions() as $definition) {
            $this->assertStringStartsWith('[activity:', $definition->getTokenString());
        }
    }

    #[DataProvider(methodName: 'provideMetricTokens')]
    public function testResolve(string $key, string $expectedValue): void
    {
        $this->assertSame(
            $expectedValue,
            $this->buildProvider(UnitSystem::METRIC)->resolve(
                $this->buildToken($key),
                $this->buildContext()
            )
        );
    }

    #[DataProvider(methodName: 'provideImperialTokens')]
    public function testResolveWithImperialUnitSystem(string $key, string $expectedValue): void
    {
        $this->assertSame(
            $expectedValue,
            $this->buildProvider(UnitSystem::IMPERIAL)->resolve(
                $this->buildToken($key),
                $this->buildContext()
            )
        );
    }

    public function testResolveStartDateWithModifier(): void
    {
        $provider = $this->buildProvider(UnitSystem::METRIC);

        $this->assertSame(
            '10-10-2023',
            $provider->resolve($this->buildToken('start-date', 'd-m-Y'), $this->buildContext())
        );
        $this->assertSame(
            '14:30',
            $provider->resolve($this->buildToken('start-date', 'H:i'), $this->buildContext())
        );
    }

    #[DataProvider(methodName: 'provideNullableTokens')]
    public function testResolveReturnsNullForUnsetFields(string $key): void
    {
        $context = TokenizerContext::empty()->with(ActivityBuilder::fromDefaults()->build());

        $this->assertNull(
            $this->buildProvider(UnitSystem::METRIC)->resolve($this->buildToken($key), $context)
        );
    }

    public function testResolveReturnsNullForUnknownKey(): void
    {
        $this->assertNull(
            $this->buildProvider(UnitSystem::METRIC)->resolve(
                $this->buildToken('pizza'),
                $this->buildContext()
            )
        );
    }

    public function testResolveReturnsNullWithoutActivityInContext(): void
    {
        $this->assertNull(
            $this->buildProvider(UnitSystem::METRIC)->resolve(
                $this->buildToken('name'),
                TokenizerContext::empty()
            )
        );
    }

    public static function provideMetricTokens(): array
    {
        return [
            ['name', 'Morning Ride'],
            ['workout-type', 'Race'],
            ['start-date', '10-10-23'],
            ['distance', '10.0 km'],
            ['elevation', '30 m'],
            ['moving-time', '1:05'],
            ['elapsed-time', '2:05'],
            ['average-speed', '28.5 km/h'],
            ['max-speed', '41.3 km/h'],
            ['average-heart-rate', '140'],
            ['max-heart-rate', '180'],
            ['average-power', '200'],
            ['max-power', '450'],
            ['average-cadence', '85'],
            ['calories', '500'],
            ['device-name', 'Garmin Edge 530'],
        ];
    }

    public static function provideImperialTokens(): array
    {
        return [
            ['distance', '6.2 mi'],
            ['elevation', '98 ft'],
            ['average-speed', '17.7 mph'],
            ['max-speed', '25.7 mph'],
        ];
    }

    public static function provideNullableTokens(): array
    {
        return [
            ['workout-type'],
            ['average-heart-rate'],
            ['max-heart-rate'],
            ['average-power'],
            ['max-power'],
            ['average-cadence'],
            ['device-name'],
        ];
    }

    private function buildProvider(UnitSystem $unitSystem): ActivityTokenProvider
    {
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository
            ->method('appearance')
            ->willReturn(AppearanceSettings::fromArray([
                'unitSystem' => $unitSystem->value,
                'dateFormat' => [
                    'short' => 'd-m-y',
                    'normal' => 'd-m-Y',
                ],
                'timeFormat' => 24,
            ]));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnArgument(0);

        return new ActivityTokenProvider(
            settingsRepository: $settingsRepository,
            translator: $translator,
        );
    }

    private function buildContext(): TokenizerContext
    {
        return TokenizerContext::empty()->with(
            ActivityBuilder::fromDefaults()
                ->withName('Morning Ride')
                ->withWorkoutType(WorkoutType::RACE)
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:30:25'))
                ->withDistance(Kilometer::from(10))
                ->withElevation(Meter::from(30))
                ->withMovingTimeInSeconds(65)
                ->withElapsedTimeInSeconds(125)
                ->withAverageSpeed(KmPerHour::from(28.53))
                ->withMaxSpeed(KmPerHour::from(41.3))
                ->withAverageHeartRate(140)
                ->withMaxHeartRate(180)
                ->withAveragePower(200)
                ->withMaxPower(450)
                ->withAverageCadence(85)
                ->withCalories(500)
                ->withDeviceName('Garmin Edge 530')
                ->build()
        );
    }

    private function buildToken(string $key, ?string $modifier = null): Token
    {
        return Token::create(
            prefix: 'activity',
            key: $key,
            modifier: $modifier,
            raw: null !== $modifier ? sprintf('[activity:%s:%s]', $key, $modifier) : sprintf('[activity:%s]', $key),
        );
    }
}
