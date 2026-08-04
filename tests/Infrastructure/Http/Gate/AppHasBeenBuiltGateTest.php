<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Application\AppStatusChecker;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Http\Gate\AppHasBeenBuiltGate;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AppHasBeenBuiltGateTest extends ContainerTestCase
{
    private ActivityIdRepository&MockObject $activityIdRepository;
    private AppStatusChecker $appStatusChecker;
    private KeyValueStore $keyValueStore;
    private UrlGeneratorInterface $urlGenerator;

    public function testItPassesThroughWhenTheAppHasBeenBuilt(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILD_SNAPSHOT,
            value: Value::fromString('2023-10-17@1.0.0'),
        ));
        $this->activityIdRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->assertFalse($this->gate()->handle(Request::create('/dashboard'))->hasBeenApplied());
    }

    public function testItRedirectsWhenNothingHasBeenBuiltYet(): void
    {
        $this->activityIdRepository->expects($this->never())->method('count');

        $response = $this->gate()->handle(Request::create('/dashboard'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/finish-setup', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testItRedirectsWhenBuiltButNoActivitiesHaveBeenImported(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILD_SNAPSHOT,
            value: Value::fromString('2023-10-17@1.0.0'),
        ));
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
        $this->activityIdRepository->expects($this->never())->method('count');

        $decision = $this->gate()->handle(Request::create('/finish-setup'));

        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    #[DataProvider('provideExemptAdminPaths')]
    public function testItKeepsTheAdminPanelReachableWhileBuildingInFileImportMode(string $path): void
    {
        $this->activityIdRepository->expects($this->never())->method('count');

        $decision = $this->gate(ImportMode::FILES)->handle(Request::create($path));

        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    #[DataProvider('provideExemptAdminPaths')]
    public function testItRedirectsTheAdminPanelWhileBuildingInStravaApiImportMode(string $path): void
    {
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
        return new AppHasBeenBuiltGate($this->urlGenerator, $importMode, $this->appStatusChecker, $this->activityIdRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityIdRepository = $this->createMock(ActivityIdRepository::class);
        $this->appStatusChecker = $this->getContainer()->get(AppStatusChecker::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
    }
}
