<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin\Settings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\Admin\AdminWebTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Component\DomCrawler\Crawler;

class AthleteWeightSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheWeightHistorySettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/athlete-weight');

        $this->assertResponseIsSuccessful();

        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="athleteWeight"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] [data-repeater]'));
        $this->assertContains('Weigh-ins', $crawler->filter('section h3')->each(fn (Crawler $node): string => $node->text()));
        $this->assertStringContainsString('Needed to calculate relative w/kg.', $crawler->filter('form[data-dispatch-command="update-settings"]')->text());
        $this->assertCount(1, $crawler->filter('input[type="checkbox"][name="data[garminWeightSyncEnabled]"]'));
        $this->assertSame(
            [
                ['on' => '2020-01-01', 'weight' => 68, 'source' => 'manual'],
                ['on' => '2019-12-01', 'weight' => 69, 'source' => 'manual'],
                ['on' => '2019-08-01', 'weight' => 70, 'source' => 'manual'],
                ['on' => '2019-07-01', 'weight' => 71, 'source' => 'manual'],
            ],
            json_decode((string) $crawler->filter('[data-repeater-list]')->attr('data-repeater-initial'), true),
        );
        $this->assertCount(2, $crawler->filter('template[data-repeater-template]'));
        $this->assertStringContainsString('data[weightHistory][__index__][weight]', $crawler->filter('template[data-repeater-template=""]')->html());

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Weight history', $selectedLink->text());
    }

    public function testItCombinesBothSourcesWithoutLosingSameDayWeighIns(): void
    {
        $settings = $this->getContainer()->get(SettingsRepository::class);
        $settings->save(SettingsName::GARMIN_WEIGHT_HISTORY, [
            ['on' => '2026-11-25', 'weightKg' => 70.125],
            ['on' => '2020-01-01', 'weightKg' => 67.5],
        ]);
        $this->client->loginUser($this->adminUser());
        $crawler = $this->client->request('GET', '/admin/settings/athlete-weight');
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('table'));
        $this->assertContains('Source', $crawler->filter('th')->each(fn (Crawler $node): string => $node->text()));
        $rows = json_decode((string) $crawler->filter('[data-repeater-list]')->attr('data-repeater-initial'), true);
        $this->assertCount(6, $rows);
        $this->assertSame(['on' => '2026-11-25', 'weight' => 70.125, 'source' => 'garmin'], $rows[0]);
        $this->assertSame(['on' => '2020-01-01', 'weight' => 68, 'source' => 'manual'], $rows[1]);
        $this->assertSame(['on' => '2020-01-01', 'weight' => 67.5, 'source' => 'garmin'], $rows[2]);

        $garmin = $crawler->filter('template[data-repeater-template="garmin"]')->html();
        $this->assertStringNotContainsString('name=', $garmin);
        $this->assertStringNotContainsString('data-repeater-remove', $garmin);
        $this->assertStringContainsString('readonly', $garmin);
    }

    public function testItConvertsOnlyGarminRowsToTheSelectedUnitSystem(): void
    {
        $settings = $this->getContainer()->get(SettingsRepository::class);
        $settings->save(SettingsName::UNIT_SYSTEM, 'imperial');
        $settings->save(SettingsName::GARMIN_WEIGHT_HISTORY, [['on' => '2026-11-25', 'weightKg' => 70]]);
        $this->client->loginUser($this->adminUser());
        $crawler = $this->client->request('GET', '/admin/settings/athlete-weight');
        $this->assertResponseIsSuccessful();
        $rows = json_decode((string) $crawler->filter('[data-repeater-list]')->attr('data-repeater-initial'), true);
        $this->assertEqualsWithDelta(154.32, $rows[0]['weight'], 0.01);
        $this->assertSame('garmin', $rows[0]['source']);
        $this->assertSame(68, $rows[1]['weight']);
        $this->assertSame('manual', $rows[1]['source']);
    }

    public function testSavingTheEditorRetainsEveryManualRecordOnTheSameDate(): void
    {
        $settings = $this->getContainer()->get(SettingsRepository::class);
        $manual = [
            ['on' => '2026-11-25', 'weight' => 70],
            ['on' => '2026-11-25', 'weight' => 71],
        ];
        $garmin = [['on' => '2026-11-25', 'weightKg' => 69.5]];
        $settings->save(SettingsName::WEIGHT_HISTORY, $manual);
        $settings->save(SettingsName::GARMIN_WEIGHT_HISTORY, $garmin);
        $this->client->loginUser($this->adminUser());
        $crawler = $this->client->request('GET', '/admin/settings/athlete-weight');
        $this->assertResponseIsSuccessful();
        $rows = Json::decode((string) $crawler->filter('[data-repeater-list]')->attr('data-repeater-initial'));
        $this->assertSame([
            ['on' => '2026-11-25', 'weight' => 70, 'source' => 'manual'],
            ['on' => '2026-11-25', 'weight' => 71, 'source' => 'manual'],
            ['on' => '2026-11-25', 'weight' => 69.5, 'source' => 'garmin'],
        ], $rows);
        $editedManual = array_map(
            static fn (array $row): array => ['on' => $row['on'], 'weight' => $row['weight']],
            array_filter($rows, static fn (array $row): bool => 'manual' === $row['source']),
        );
        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::ATHLETE_WEIGHT->value,
            'data' => ['weightHistory' => $editedManual, 'garminWeightSyncEnabled' => true],
        ]));
        $connection = $this->getContainer()->get(Connection::class);
        $this->assertSame($manual, Json::decode($connection->fetchOne('SELECT value FROM Setting WHERE name = ?', ['weightHistory'])));
        $this->assertSame($garmin, Json::decode($connection->fetchOne('SELECT value FROM Setting WHERE name = ?', ['garminWeightHistory'])));
        $effective = array_values($settings->athleteWeight()->getHistory(UnitSystem::METRIC)->findAll());
        $this->assertSame(71.0, $effective[0]->getWeight()->toFloat());
    }
}
