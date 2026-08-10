<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Http\Gate\AppHasActivitiesGate;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AppHasActivitiesGateTest extends ContainerTestCase
{
    private ActivityIdRepository&MockObject $activityIdRepository;
    private UrlGeneratorInterface $urlGenerator;

    public function testItPassesThroughWhenActivitiesHaveBeenImported(): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->assertFalse(new AppHasActivitiesGate($this->urlGenerator, ImportMode::FILES, $this->activityIdRepository)->handle(Request::create('/dashboard'))->hasBeenApplied());
    }

    public function testItRedirectsWhenNoActivitiesHaveBeenImported(): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $response = new AppHasActivitiesGate($this->urlGenerator, ImportMode::FILES, $this->activityIdRepository)->handle(Request::create('/dashboard'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/finish-setup', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testItNeverRedirectsTheFinishSetupTargetItself(): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $decision = new AppHasActivitiesGate($this->urlGenerator, ImportMode::FILES, $this->activityIdRepository)->handle(Request::create('/finish-setup'));

        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    #[DataProvider('provideExemptAdminPaths')]
    public function testItKeepsTheAdminPanelReachableInFileImportMode(string $path): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $decision = new AppHasActivitiesGate($this->urlGenerator, ImportMode::FILES, $this->activityIdRepository)->handle(Request::create($path));

        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    #[DataProvider('provideExemptAdminPaths')]
    public function testItRedirectsTheAdminPanelInStravaApiImportMode(string $path): void
    {
        $this->activityIdRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $response = new AppHasActivitiesGate($this->urlGenerator, ImportMode::STRAVA_API, $this->activityIdRepository)->handle(Request::create($path))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/finish-setup', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public static function provideExemptAdminPaths(): iterable
    {
        yield 'admin root' => ['/admin'];
        yield 'admin sub path' => ['/admin/settings/general'];
        yield 'admin login' => ['/admin/login'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityIdRepository = $this->createMock(ActivityIdRepository::class);
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
    }
}
