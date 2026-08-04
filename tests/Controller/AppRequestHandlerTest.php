<?php

namespace App\Tests\Controller;

use App\Application\AppStatusChecker;
use App\Application\IndexPage;
use App\Controller\AppRequestHandler;
use App\Infrastructure\Http\Page\PageRenderer;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
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
        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILD_SNAPSHOT,
            value: Value::fromString('2023-10-17@1.0.0'),
        ));

        $this->assertMatchesHtmlSnapshot($this->appRequestHandler->handle()->getContent());
    }

    public function testHandleThrowsWhenTheAppHasNotBeenBuilt(): void
    {
        $this->expectExceptionObject(new NotFoundHttpException('Not found'));

        $this->appRequestHandler->handle();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->appRequestHandler = new AppRequestHandler(
            $this->getContainer()->get(AppStatusChecker::class),
            $this->getContainer()->get(IndexPage::class),
            $this->getContainer()->get(PageRenderer::class),
        );
    }
}
