<?php

namespace App\Tests\Controller;

use App\Application\AppShell;
use App\Application\NotFoundFragment;
use App\Controller\AppRequestHandler;
use App\Domain\Activity\ActivityIdRepository;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
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

    public function testHandleRendersTheNotFoundPageForAnUnknownPath(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->appRequestHandler->handle('dmzdmzd')->getContent());
    }

    public function testHandleThrowsWhenNoActivitiesHaveBeenImported(): void
    {
        $this->expectExceptionObject(new NotFoundHttpException('Not found'));

        $this->appRequestHandler->handle();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideWildcards')]
    public function testItAnswersWithTheStatusCodeOfThePageTheWildcardPointsAt(?string $wildcard, int $expectedStatusCode): void
    {
        $this->provideFullTestSet();

        $this->assertEquals(
            $expectedStatusCode,
            $this->appRequestHandler->handle($wildcard)->getStatusCode()
        );
    }

    public static function provideWildcards(): iterable
    {
        yield 'the root renders the not found page' => [null, 404];
        yield 'an unknown page' => ['dmzdmzd', 404];
        yield 'an unknown page below a known one' => ['dashboard/dmzdmzd', 404];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->appRequestHandler = new AppRequestHandler(
            $this->getContainer()->get(ActivityIdRepository::class),
            $this->getContainer()->get(AppShell::class),
            $this->getContainer()->get(FragmentRegistry::class),
            $this->getContainer()->get(FragmentRenderer::class),
            $this->getContainer()->get(NotFoundFragment::class),
        );
    }
}
