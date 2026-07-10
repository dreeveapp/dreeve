<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Domain\Settings\AthleteHasNotBeenConfigured;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Http\Gate\ValidAppSettingsGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidAppSettingsGateTest extends TestCase
{
    private SettingsRepository&MockObject $settingsRepository;

    public function testItPassesThroughWhenTheAthleteHasBeenConfigured(): void
    {
        $this->settingsRepository
            ->expects($this->once())
            ->method('general')
            ->willReturn(GeneralSettings::fromArray([
                'athlete' => [
                    'birthday' => '1989-08-14',
                    'maxHeartRateFormula' => 'fox',
                ],
            ]));

        $this->assertNull($this->gate()->handle(Request::create('/dashboard')));
    }

    public function testItRedirectsWhenTheAthleteHasNotBeenConfigured(): void
    {
        $this->settingsRepository
            ->expects($this->once())
            ->method('general')
            ->willThrowException(AthleteHasNotBeenConfigured::because('nope'));

        $response = $this->gate()->handle(Request::create('/dashboard'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin/settings', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    #[DataProvider('provideExemptSettingsPaths')]
    public function testItNeverRedirectsTheSettingsPage(string $path): void
    {
        $this->settingsRepository
            ->expects($this->once())
            ->method('general')
            ->willThrowException(AthleteHasNotBeenConfigured::because('nope'));

        $this->assertNull($this->gate()->handle(Request::create($path)));
    }

    public static function provideExemptSettingsPaths(): iterable
    {
        yield 'the redirect target itself' => ['/admin/settings'];
        yield 'a settings group sub path' => ['/admin/settings/general'];
    }

    private function gate(): ValidAppSettingsGate
    {
        return new ValidAppSettingsGate($this->settingsRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->settingsRepository = $this->createMock(SettingsRepository::class);
    }
}
