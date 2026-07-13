<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Http\Gate\AppHasBeenBuiltGate;
use App\Tests\ContainerTestCase;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AppHasBeenBuiltGateTest extends ContainerTestCase
{
    private FilesystemOperator&MockObject $buildHtmlStorage;
    private ActivityIdRepository&MockObject $activityIdRepository;
    private UrlGeneratorInterface $urlGenerator;

    public function testItPassesThroughWhenTheAppHasBeenBuilt(): void
    {
        $this->buildHtmlStorage
            ->expects($this->once())
            ->method('fileExists')
            ->with('index.html')
            ->willReturn(true);
        $this->activityIdRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->assertFalse($this->gate()->handle(Request::create('/dashboard'))->hasBeenApplied());
    }

    public function testItRedirectsWhenNothingHasBeenBuiltYet(): void
    {
        $this->buildHtmlStorage
            ->expects($this->once())
            ->method('fileExists')
            ->with('index.html')
            ->willReturn(false);
        $this->activityIdRepository->expects($this->never())->method('count');

        $response = $this->gate()->handle(Request::create('/dashboard'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/finish-setup', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testItRedirectsWhenBuiltButNoActivitiesHaveBeenImported(): void
    {
        $this->buildHtmlStorage
            ->expects($this->once())
            ->method('fileExists')
            ->with('index.html')
            ->willReturn(true);
        $this->activityIdRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $response = $this->gate()->handle(Request::create('/dashboard'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/finish-setup', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testItNeverRedirectsTheFinishSetupTargetItself(): void
    {
        $this->buildHtmlStorage
            ->expects($this->once())
            ->method('fileExists')
            ->with('index.html')
            ->willReturn(false);
        $this->activityIdRepository->expects($this->never())->method('count');

        $decision = $this->gate()->handle(Request::create('/finish-setup'));

        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    #[DataProvider('provideExemptAdminPaths')]
    public function testItKeepsTheAdminPanelReachableWhileBuildingInFileImportMode(string $path): void
    {
        $this->buildHtmlStorage
            ->expects($this->once())
            ->method('fileExists')
            ->with('index.html')
            ->willReturn(false);
        $this->activityIdRepository->expects($this->never())->method('count');

        $decision = $this->gate(ImportMode::FILES)->handle(Request::create($path));

        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    #[DataProvider('provideExemptAdminPaths')]
    public function testItRedirectsTheAdminPanelWhileBuildingInStravaApiImportMode(string $path): void
    {
        $this->buildHtmlStorage
            ->expects($this->once())
            ->method('fileExists')
            ->with('index.html')
            ->willReturn(false);
        $this->activityIdRepository->expects($this->never())->method('count');

        $response = $this->gate(ImportMode::STRAVA_API)->handle(Request::create($path))->getResponse();

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

    private function gate(ImportMode $importMode = ImportMode::FILES): AppHasBeenBuiltGate
    {
        return new AppHasBeenBuiltGate($this->urlGenerator, $importMode, $this->buildHtmlStorage, $this->activityIdRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildHtmlStorage = $this->createMock(FilesystemOperator::class);
        $this->activityIdRepository = $this->createMock(ActivityIdRepository::class);
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
    }
}
