<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Domain\Settings\AthleteHasNotBeenConfigured;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Http\Gate\ValidAppSettingsGate;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ValidAppSettingsGateTest extends ContainerTestCase
{
    private SettingsRepository&MockObject $settingsRepository;
    private UrlGeneratorInterface $urlGenerator;

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

        $this->assertFalse($this->gate()->handle(Request::create('/dashboard'))->hasBeenApplied());
    }

    public function testItRedirectsWhenTheAthleteHasNotBeenConfigured(): void
    {
        $this->settingsRepository
            ->expects($this->once())
            ->method('general')
            ->willThrowException(AthleteHasNotBeenConfigured::because('nope'));

        $response = $this->gate()->handle(Request::create('/dashboard'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin/settings/athlete', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    #[DataProvider('provideExemptPaths')]
    public function testItNeverRedirectsTheAthleteSettingsPageNorTheseEssentials(string $path): void
    {
        $this->settingsRepository
            ->expects($this->once())
            ->method('general')
            ->willThrowException(AthleteHasNotBeenConfigured::because('nope'));

        $decision = $this->gate()->handle(Request::create($path));

        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    public static function provideExemptPaths(): iterable
    {
        yield 'the redirect target itself' => ['/admin/settings/athlete'];
        yield 'the login page' => ['/admin/login'];
        yield 'the logout endpoint' => ['/admin/logout'];
        yield 'the command endpoint the athlete form posts to' => ['/admin/dispatchCommand'];
    }

    #[DataProvider('provideGuardedAdminPaths')]
    public function testItRedirectsEveryOtherAdminPage(string $path): void
    {
        $this->settingsRepository
            ->expects($this->once())
            ->method('general')
            ->willThrowException(AthleteHasNotBeenConfigured::because('nope'));

        $response = $this->gate()->handle(Request::create($path))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin/settings/athlete', $response->getTargetUrl());
    }

    public static function provideGuardedAdminPaths(): iterable
    {
        yield 'the settings index' => ['/admin/settings'];
        yield 'another settings group' => ['/admin/settings/general'];
        yield 'the file upload page' => ['/admin/upload'];
    }

    private function gate(): ValidAppSettingsGate
    {
        return new ValidAppSettingsGate($this->urlGenerator, $this->settingsRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->createMock(SettingsRepository::class);
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
    }
}
