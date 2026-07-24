<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearTokenProvider;
use App\Domain\Settings\AppearanceSettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Tokenizer\Token;
use App\Infrastructure\Tokenizer\TokenizerContext;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GearTokenProviderTest extends TestCase
{
    private GearRepository&MockObject $gearRepository;

    public function testGetPrefix(): void
    {
        $provider = $this->buildProvider(UnitSystem::METRIC);
        $this->gearRepository
            ->expects($this->never())
            ->method('find');

        $this->assertSame('gear', $provider->getPrefix());
    }

    public function testGetTokenDefinitions(): void
    {
        $provider = $this->buildProvider(UnitSystem::METRIC);
        $this->gearRepository
            ->expects($this->never())
            ->method('find');

        foreach ($provider->getTokenDefinitions() as $definition) {
            $this->assertStringStartsWith('[gear:', $definition->getTokenString());
        }
    }

    #[DataProvider(methodName: 'provideTokens')]
    public function testResolve(string $key, string $expectedValue): void
    {
        $provider = $this->buildProvider(UnitSystem::METRIC);
        $this->gearRepository
            ->expects($this->once())
            ->method('find')
            ->with(GearId::fromUnprefixed('1'))
            ->willReturn(
                GearBuilder::fromDefaults()
                    ->withName('Canyon Ultimate')
                    ->withDistanceInMeter(Meter::from(10023))
                    ->withNumberOfActivities(12)
                    ->build()
            );

        $this->assertSame(
            $expectedValue,
            $provider->resolve($this->buildToken($key), $this->buildContext())
        );
    }

    public function testResolveWithImperialUnitSystem(): void
    {
        $provider = $this->buildProvider(UnitSystem::IMPERIAL);
        $this->gearRepository
            ->expects($this->once())
            ->method('find')
            ->with(GearId::fromUnprefixed('1'))
            ->willReturn(
                GearBuilder::fromDefaults()
                    ->withDistanceInMeter(Meter::from(10023))
                    ->build()
            );

        $this->assertSame(
            '6 mi',
            $provider->resolve($this->buildToken('distance'), $this->buildContext())
        );
    }

    public function testResolveReturnsNullWithoutGearId(): void
    {
        $provider = $this->buildProvider(UnitSystem::METRIC);
        $this->gearRepository
            ->expects($this->never())
            ->method('find');

        $context = TokenizerContext::empty()->with(
            ActivityBuilder::fromDefaults()->withoutGearId()->build()
        );

        $this->assertNull($provider->resolve($this->buildToken('name'), $context));
    }

    public function testResolveReturnsNullWhenGearNotFound(): void
    {
        $provider = $this->buildProvider(UnitSystem::METRIC);
        $this->gearRepository
            ->expects($this->once())
            ->method('find')
            ->with(GearId::fromUnprefixed('1'))
            ->willThrowException(new EntityNotFound('Gear "1" not found'));

        $this->assertNull($provider->resolve($this->buildToken('name'), $this->buildContext()));
    }

    public function testResolveReturnsNullWithoutActivityInContext(): void
    {
        $provider = $this->buildProvider(UnitSystem::METRIC);
        $this->gearRepository
            ->expects($this->never())
            ->method('find');

        $this->assertNull($provider->resolve($this->buildToken('name'), TokenizerContext::empty()));
    }

    public function testResolveReturnsNullForUnknownKey(): void
    {
        $provider = $this->buildProvider(UnitSystem::METRIC);
        $this->gearRepository
            ->expects($this->once())
            ->method('find')
            ->with(GearId::fromUnprefixed('1'))
            ->willReturn(GearBuilder::fromDefaults()->build());

        $this->assertNull($provider->resolve($this->buildToken('pizza'), $this->buildContext()));
    }

    public static function provideTokens(): array
    {
        return [
            ['name', 'Canyon Ultimate'],
            ['distance', '10 km'],
            ['number-of-activities', '12'],
        ];
    }

    private function buildProvider(UnitSystem $unitSystem): GearTokenProvider
    {
        $this->gearRepository = $this->createMock(GearRepository::class);

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

        return new GearTokenProvider(
            gearRepository: $this->gearRepository,
            settingsRepository: $settingsRepository,
        );
    }

    private function buildContext(): TokenizerContext
    {
        return TokenizerContext::empty()->with(
            ActivityBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->build()
        );
    }

    private function buildToken(string $key): Token
    {
        return Token::create(
            prefix: 'gear',
            key: $key,
            modifier: null,
            raw: sprintf('[gear:%s]', $key),
        );
    }
}
