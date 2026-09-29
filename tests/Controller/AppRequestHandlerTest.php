<?php

namespace App\Tests\Controller;

use App\Application\IndexPage;
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

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->appRequestHandler->handle()->getContent());
    }

    public function testHandleRendersTheNotFoundPageForAnUnknownPath(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->appRequestHandler->handle('dmzdmzd')->getContent());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideActiveSections')]
    public function testHandleMarksTheSectionOfThePageAsActive(string $wildcard, ?string $expectedActiveHref): void
    {
        $this->provideFullTestSet();

        $content = (string) $this->appRequestHandler->handle($wildcard)->getContent();

        $this->assertSame(null === $expectedActiveHref ? 0 : 1, substr_count($content, 'aria-selected="true"'));
        if (null !== $expectedActiveHref) {
            $this->assertStringContainsString(sprintf('href="%s" aria-selected="true"', $expectedActiveHref), $content);
        }
    }

    public static function provideActiveSections(): iterable
    {
        yield 'the dashboard' => ['dashboard', '/dashboard'];
        yield 'a page below the dashboard' => ['dashboard/power-output', '/dashboard'];
        yield 'an activity' => ['activities/activity-9756441741', '/activities'];
        yield 'a segment' => ['segments/segment-1', '/segments'];
        yield 'a page below gear' => ['gear/maintenance', '/gear'];
        yield 'a month' => ['monthly-stats/2023-06', '/monthly-stats'];
        yield 'a rewind comparison' => ['rewind/2023/compare/2022', '/rewind'];
        yield 'a best effort history' => ['best-efforts/Ride/10000', '/best-efforts'];
        yield 'a page without a menu item' => ['badges', null];
        yield 'an unknown page' => ['dmzdmzd', null];
    }

    public function testHandleKeepsThePageCacheControl(): void
    {
        $this->provideFullTestSet();

        $this->assertEquals(
            'no-store, private',
            $this->appRequestHandler->handle('dashboard')->headers->get('Cache-Control'),
        );
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
        yield 'an activity' => ['activities/activity-9756441741', 200];
        yield 'an unknown activity' => ['activities/activity-1', 404];
        yield 'a segment' => ['segments/segment-1', 200];
        yield 'an unknown segment' => ['segments/segment-999', 404];
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
            $this->getContainer()->get(NotFoundFragment::class),
        );
    }
}
