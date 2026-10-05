<?php

namespace App\Tests\Controller\Page\Calendar;

use App\Controller\Page\Calendar\MonthRequestHandler;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MonthRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/monthly-stats/2023-06');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'monthly-stats.2023-06',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderJanuary(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/monthly-stats/2023-01');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsTaggedWithTheMonthsItRenders(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/monthly-stats/2023-01');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities.2022-12, activities.2023-01, activities.2023-02',
        );
    }

    public function testItResolvesEveryMonthBetweenTheFirstActivityAndToday(): void
    {
        // The clock is paused on 2023-10-17, the test set starts in July 2020.
        $this->provideFullTestSet();

        $this->client->request('GET', '/monthly-stats/2020-07');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/monthly-stats/2023-10');
        $this->assertResponseIsSuccessful();
    }

    public function testItDoesNotResolveMonthsOutsideThatRange(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/monthly-stats/2020-06');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/monthly-stats/2023-11');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveMalformedPaths(): void
    {
        $this->provideFullTestSet();

        foreach (['2023-13', '2023-6', 'not-a-month', '2023-06/extra'] as $month) {
            $this->client->request('GET', '/monthly-stats/'.$month);
            $this->assertResponseStatusCodeSame(404);
        }
    }

    /**
     * AppHasActivitiesGate redirects every request while there are no activities, so an empty
     * database can only be observed through the handler.
     */
    public function testItDoesNotResolveWhenThereAreNoActivities(): void
    {
        $this->expectExceptionObject(new NotFoundHttpException('Not found'));

        $this->getContainer()->get(MonthRequestHandler::class)->handle('2023-06');
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
