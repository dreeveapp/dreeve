<?php

declare(strict_types=1);

namespace App\Domain\Activity\Eddington\Config;

use App\Domain\Activity\Eddington\InvalidEddingtonConfiguration;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<EddingtonConfigItem>
 */
final class EddingtonConfiguration extends Collection
{
    public function getItemClassName(): string
    {
        return EddingtonConfigItem::class;
    }

    /**
     * @return array<int, mixed>
     */
    public static function getDefaultConfig(): array
    {
        return [
            [
                'label' => 'Ride',
                'showInNavBar' => true,
                'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide'],
                'showInDashboardWidget' => true,
            ],
            [
                'label' => 'Run',
                'showInNavBar' => true,
                'sportTypesToInclude' => ['Run', 'TrailRun', 'VirtualRun'],
                'showInDashboardWidget' => true,
            ],
            [
                'label' => 'Walk',
                'showInNavBar' => false,
                'sportTypesToInclude' => ['Walk', 'Hike'],
                'showInDashboardWidget' => false,
            ],
        ];
    }

    /**
     * @param array<int, mixed> $items
     */
    public static function fromScalarArray(array $items): self
    {
        if ([] === $items) {
            // Make sure this new feature is backwards compatible.
            // Use the old default configuration.
            $items = self::getDefaultConfig();
        }

        $eddingtonConfigItems = [];
        foreach ($items as $eddingtonConfig) {
            $sportTypesToInclude = SportTypes::empty();

            if (!is_array($eddingtonConfig)) {
                throw new InvalidEddingtonConfiguration('Invalid Eddington configuration provided');
            }

            foreach (['label', 'showInNavBar', 'sportTypesToInclude'] as $requiredKey) {
                if (array_key_exists($requiredKey, $eddingtonConfig)) {
                    continue;
                }
                throw new InvalidEddingtonConfiguration(sprintf('"%s" property is required', $requiredKey));
            }

            if (empty($eddingtonConfig['label'])) {
                throw new InvalidEddingtonConfiguration('"label" property cannot be empty');
            }

            if (!is_bool($eddingtonConfig['showInNavBar'])) {
                throw new InvalidEddingtonConfiguration('"showInNavBar" property must be a boolean');
            }

            if (array_key_exists('showInDashboardWidget', $eddingtonConfig) && !is_bool($eddingtonConfig['showInDashboardWidget'])) {
                throw new InvalidEddingtonConfiguration('"showInDashboardWidget" property must be a boolean');
            }

            if (!is_array($eddingtonConfig['sportTypesToInclude'])) {
                throw new InvalidEddingtonConfiguration('"sportTypesToInclude" property must be an array');
            }

            if (empty($eddingtonConfig['sportTypesToInclude'])) {
                throw new InvalidEddingtonConfiguration('"sportTypesToInclude" property cannot be empty');
            }

            foreach ($eddingtonConfig['sportTypesToInclude'] as $sportTypeToInclude) {
                if (!$sportType = SportType::tryFrom($sportTypeToInclude)) {
                    throw new InvalidEddingtonConfiguration(sprintf('"%s" is not a valid sport type', $sportTypeToInclude));
                }
                $sportTypesToInclude->add($sportType);
            }

            $uniqueActivityTypes = array_unique(array_map(
                static fn (string $sportType) => SportType::from($sportType)->getActivityType()->value,
                $eddingtonConfig['sportTypesToInclude']
            ));

            if (1 !== count($uniqueActivityTypes)) {
                throw new InvalidEddingtonConfiguration(sprintf('Eddington "%s" contains sport types with different activity types', $eddingtonConfig['label']));
            }

            $eddingtonConfigItems[] = EddingtonConfigItem::create(
                label: $eddingtonConfig['label'],
                showInNavBar: $eddingtonConfig['showInNavBar'],
                sportTypesToInclude: $sportTypesToInclude,
                showInDashboardWidget: $eddingtonConfig['showInDashboardWidget'] ?? false,
            );
        }

        if (count(array_filter($items, fn (array $eddingtonConfig) => $eddingtonConfig['showInNavBar'])) > 2) {
            throw new InvalidEddingtonConfiguration('You can only have two Eddingtons with "showInNavBar" set to true');
        }
        if (count(array_filter($items, fn (array $eddingtonConfig): bool => array_key_exists('showInDashboardWidget', $eddingtonConfig) && $eddingtonConfig['showInDashboardWidget'])) > 2) {
            throw new InvalidEddingtonConfiguration('You can only have two Eddingtons with "showInDashboardWidget" set to true');
        }

        return parent::fromArray($eddingtonConfigItems);
    }
}
