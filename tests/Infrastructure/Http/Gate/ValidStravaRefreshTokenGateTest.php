<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Import\ImportMode;
use App\Domain\Strava\InvalidStravaAccessToken;
use App\Domain\Strava\Strava;
use App\Domain\Strava\StravaApplicationIsInactive;
use App\Infrastructure\Http\Gate\ValidStravaRefreshTokenGate;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ValidStravaRefreshTokenGateTest extends ContainerTestCase
{
    private Strava&MockObject $strava;
    private ActivityIdRepository&MockObject $activityIdRepository;
    private UrlGeneratorInterface $urlGenerator;

    public function testItPassesThroughWhenNotUsingStravaApiImportMode(): void
    {
        $this->strava->expects($this->never())->method('verifyAccessToken');
        $this->activityIdRepository->expects($this->never())->method('hasImportedFromStravaApi');

        $gate = new ValidStravaRefreshTokenGate(
            $this->urlGenerator,
            ImportMode::FILES,
            $this->strava,
            $this->activityIdRepository,
        );

        $this->assertFalse($gate->handle(Request::create('/dashboard'))->hasBeenApplied());
    }

    public function testItPassesThroughWhenAStravaApiActivityHasBeenImported(): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('hasImportedFromStravaApi')
            ->willReturn(true);
        $this->strava->expects($this->never())->method('verifyAccessToken');

        $gate = new ValidStravaRefreshTokenGate(
            $this->urlGenerator,
            ImportMode::STRAVA_API,
            $this->strava,
            $this->activityIdRepository,
        );

        $this->assertFalse($gate->handle(Request::create('/dashboard'))->hasBeenApplied());
    }

    public function testItRedirectsWhenTheRefreshTokenCanNotBeVerified(): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('hasImportedFromStravaApi')
            ->willReturn(false);
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken')
            ->willThrowException(new InvalidStravaAccessToken());

        $gate = new ValidStravaRefreshTokenGate(
            $this->urlGenerator,
            ImportMode::STRAVA_API,
            $this->strava,
            $this->activityIdRepository,
        );

        $response = $gate->handle(Request::create('/dashboard'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/strava-oauth', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testItRedirectsWhenTheStravaApplicationIsInactive(): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('hasImportedFromStravaApi')
            ->willReturn(false);
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken')
            ->willThrowException(StravaApplicationIsInactive::create());

        $gate = new ValidStravaRefreshTokenGate(
            $this->urlGenerator,
            ImportMode::STRAVA_API,
            $this->strava,
            $this->activityIdRepository,
        );

        $response = $gate->handle(Request::create('/dashboard'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/strava-oauth', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testItPassesThroughWhenTheRefreshTokenIsValid(): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('hasImportedFromStravaApi')
            ->willReturn(false);
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken');

        $gate = new ValidStravaRefreshTokenGate(
            $this->urlGenerator,
            ImportMode::STRAVA_API,
            $this->strava,
            $this->activityIdRepository,
        );

        $this->assertFalse($gate->handle(Request::create('/dashboard'))->hasBeenApplied());
    }

    public function testItNeverRedirectsTheOAuthTargetItself(): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('hasImportedFromStravaApi')
            ->willReturn(false);
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken')
            ->willThrowException(new InvalidStravaAccessToken());

        $gate = new ValidStravaRefreshTokenGate(
            $this->urlGenerator,
            ImportMode::STRAVA_API,
            $this->strava,
            $this->activityIdRepository,
        );

        $decision = $gate->handle(Request::create('/strava-oauth'));

        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->strava = $this->createMock(Strava::class);
        $this->activityIdRepository = $this->createMock(ActivityIdRepository::class);
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
    }
}
