<?php

namespace App\Tests\Controller;

use App\Application\IndexPage;
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
        yield 'the root renders the default page' => [null, 200];
        yield 'an empty wildcard renders the default page' => ['', 200];
        yield 'a known page' => ['dashboard', 200];
        yield 'a known nested page' => ['gear/maintenance', 200];
        yield 'an unknown page' => ['dmzdmzd', 404];
        yield 'an unknown page below a known one' => ['dashboard/dmzdmzd', 404];
        yield 'a data fragment is not a page' => ['heatmap/routes', 404];
        yield 'the countries data fragment is not a page' => ['heatmap/countries', 404];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->appRequestHandler = new AppRequestHandler(
            $this->getContainer()->get(ActivityIdRepository::class),
            $this->getContainer()->get(IndexPage::class),
            $this->getContainer()->get(FragmentRegistry::class),
            $this->getContainer()->get(FragmentRenderer::class),
        );
    }
}
