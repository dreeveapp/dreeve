<?php

namespace App\Tests\Controller;

use App\Application\IndexPage;
use App\Controller\AppRequestHandler;
use App\Domain\Activity\ActivityIdRepository;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppRequestHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private AppRequestHandler $appRequestHandler;

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->appRequestHandler->handle()->getContent());
    }

    public function testHandleThrowsWhenNoActivitiesHaveBeenImported(): void
    {
        $this->expectExceptionObject(new NotFoundHttpException('Not found'));

        $this->appRequestHandler->handle();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->appRequestHandler = new AppRequestHandler(
            $this->getContainer()->get(ActivityIdRepository::class),
            $this->getContainer()->get(IndexPage::class),
            $this->getContainer()->get(FragmentRenderer::class),
        );
    }
}
