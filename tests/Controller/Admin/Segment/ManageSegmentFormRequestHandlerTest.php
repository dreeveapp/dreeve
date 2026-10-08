<?php

namespace App\Tests\Controller\Admin\Segment;

use App\Domain\Import\ImportMode;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class ManageSegmentFormRequestHandlerTest extends AdminWebTestCase
{
    public function testRendersTheAddForm(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments/add');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('head link[rel="stylesheet"][href*="libraries/leaflet/leaflet.min.css"]'));
        $form = $crawler->filter('form[data-dispatch-command="add-custom-segment"]');
        $this->assertCount(1, $form);
        $this->assertStringEndsWith('/admin/segments', (string) $form->attr('data-redirect'));
        $this->assertStringEndsWith('/admin/activities/search', (string) $form->filter('#segment-activity')->attr('data-autocomplete-url'));
        $this->assertCount(1, $form->filter('input[type="hidden"][name="startIndex"]'));
        $this->assertCount(1, $form->filter('input[type="hidden"][name="endIndex"]'));
        $this->assertCount(1, $form->filter('input[name="name"][required]'));
        $this->assertCount(1, $form->filter('input[type="checkbox"][name="isFavourite"][value="true"]'));
        $this->assertStringContainsString('Efforts are not calculated right away.', $form->filter('[role="note"]')->text());

        $picker = $form->filter('[data-route-range-picker]');
        $this->assertSame('empty', $picker->attr('data-state'));
        $options = Json::decode((string) $picker->attr('data-route-range-picker'));
        $this->assertStringEndsWith('/admin/activities/activity-__ID__/route', $options['url']);
        $this->assertSame('activity-__ID__', $options['placeholder']);
        $this->assertSame('#segment-activity', $options['source']);
        $this->assertEquals(1000, $options['distanceInMetersPerUnit']);
        $this->assertEquals(1, $options['elevationInMetersPerUnit']);
    }

    public function testItIsNotAvailableInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/segments/add');

        $this->assertResponseStatusCodeSame(404);
    }

    #[DataProvider('provideRedirectToQueryParams')]
    public function testRendersTheEditFormPrefilledWithTheSegment(string $query, string $expectedRedirect): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Kwaremont'))
            ->withIsFavourite(true)
            ->withType(SegmentType::CUSTOM)
            ->build());
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments/segment-1/edit'.$query);

        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[data-dispatch-command="update-segment"]');
        $this->assertCount(1, $form);
        $this->assertSame('segment-1', $form->filter('input[name="segmentId"]')->attr('value'));
        $this->assertSame('Kwaremont', $form->filter('input[name="name"]')->attr('value'));
        $this->assertCount(1, $form->filter('input[name="isFavourite"][checked]'));
        $this->assertSame($expectedRedirect, $form->attr('data-redirect'));
        $this->assertSame($expectedRedirect, $crawler->filter('a[aria-label="Close"]')->attr('href'));
        $this->assertSame($expectedRedirect, $crawler->filter('.btn--secondary')->attr('href'));
        $this->assertSame('/admin/segments/segment-1/delete', $form->filter('a.btn--danger')->attr('href'));
    }

    #[DataProvider('provideRedirectToQueryParams')]
    public function testRendersTheDeleteConfirmation(string $query, string $expectedRedirect): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Kwaremont'))
            ->withType(SegmentType::IMPORTED)
            ->build());
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments/segment-1/delete'.$query);

        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[data-dispatch-command="delete-segment"]');
        $this->assertCount(1, $form);
        $this->assertSame('segment-1', $form->filter('input[name="segmentId"]')->attr('value'));
        $this->assertStringContainsString('Are you sure you want to delete Kwaremont?', $form->text());
        $this->assertSame($expectedRedirect, $form->attr('data-redirect'));
        $this->assertSame($expectedRedirect, $crawler->filter('a[aria-label="Close"]')->attr('href'));
        $this->assertSame($expectedRedirect, $crawler->filter('.btn--secondary')->attr('href'));
    }

    public static function provideRedirectToQueryParams(): iterable
    {
        yield 'no redirectTo' => ['', '/admin/segments'];
        yield 'a filtered overview' => ['?redirectTo='.urlencode('/admin/segments?filters%5Bname%5D=kwa&pagination%5Bpage%5D=2'), '/admin/segments?filters%5Bname%5D=kwa&pagination%5Bpage%5D=2'];
        yield 'absolute url' => ['?redirectTo='.urlencode('https://evil.com'), '/admin/segments'];
    }

    #[DataProvider('provideUnavailablePages')]
    public function testUnavailablePagesAreNotFound(ImportMode $importMode, SegmentType $type, string $path): void
    {
        $this->withImportMode($importMode);
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withType($type)
            ->build());
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', $path);

        $this->assertResponseStatusCodeSame(404);
    }

    public static function provideUnavailablePages(): iterable
    {
        yield 'editing a Strava segment' => [ImportMode::FILES, SegmentType::IMPORTED, '/admin/segments/segment-1/edit'];
        yield 'editing an unknown segment' => [ImportMode::FILES, SegmentType::CUSTOM, '/admin/segments/segment-2/edit'];
        yield 'deleting an unknown segment' => [ImportMode::FILES, SegmentType::CUSTOM, '/admin/segments/segment-2/delete'];
        yield 'editing in Strava API mode' => [ImportMode::STRAVA_API, SegmentType::CUSTOM, '/admin/segments/segment-1/edit'];
        yield 'deleting in Strava API mode' => [ImportMode::STRAVA_API, SegmentType::CUSTOM, '/admin/segments/segment-1/delete'];
    }
}
