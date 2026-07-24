<?php

namespace App\Tests\Controller\Admin\Gear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\Measurement\Length\Meter;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Gear\GearBuilder;

class ManageGearOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/gear');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRendersTheEmptyStateWhenThereAreNoGears(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('table.data-table'));
        $this->assertStringContainsString('No gears added yet.', $crawler->filter('body')->text());
        $this->assertCount(1, $crawler->filter('table.data-table tbody td[colspan="6"]'));
        $this->assertCount(0, $crawler->filter('table.data-table tbody a[title="Edit"]'));
    }

    public function testRendersTheTableWithGears(): void
    {
        $importedGearRepository = static::getContainer()->get(GearRepository::class);

        for ($i = 1; $i <= 3; ++$i) {
            $importedGearRepository->add(
                GearBuilder::fromDefaults()
                    ->withGearId(GearId::fromUnprefixed((string) $i))
                    ->withName(sprintf('Gear %d', $i))
                    ->withDistanceInMeter(Meter::from(1000 * $i))
                    ->build()
            );
        }

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear');

        $this->assertResponseIsSuccessful();
        $this->assertCount(3, $crawler->filter('table.data-table tbody tr'));
        $this->assertStringContainsString('Gear 1', $crawler->filter('table.data-table')->text());
        $this->assertStringNotContainsString('No gears added yet.', $crawler->filter('body')->text());

        $editLinks = $crawler->filter('table.data-table tbody a[title="Edit"]');
        $this->assertCount(3, $editLinks);
        $this->assertStringContainsString(
            '/admin/gear/'.GearId::fromUnprefixed('1').'/edit',
            $editLinks->first()->attr('href')
        );
    }

    public function testRendersTheGearImageWithPlaceholderFallback(): void
    {
        $gearRepository = static::getContainer()->get(GearRepository::class);
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('Gear with image')
                ->withLocalImagePath('files/gear/image.jpg')
                ->build()
        );
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withName('Gear without image')
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear');

        $this->assertResponseIsSuccessful();
        $images = $crawler->filter('table.data-table tbody tr td:first-child img');
        $this->assertCount(2, $images);

        $sources = $images->each(fn ($img): ?string => $img->attr('src'));
        $this->assertContains('/files/gear/image.jpg', $sources);
        $placeholderSources = array_filter($sources, fn (?string $src): bool => str_contains((string) $src, 'placeholder'));
        $this->assertCount(1, $placeholderSources);
    }
}
