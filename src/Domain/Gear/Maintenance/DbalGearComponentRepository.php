<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Media\MediaItem;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Connection;

final readonly class DbalGearComponentRepository extends DbalRepository implements GearComponentRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
        private MediaItemRepository $mediaItemRepository,
    ) {
        parent::__construct($connection);
    }

    public function add(GearComponent $gearComponent): void
    {
        $sql = 'INSERT INTO GearComponent (gearComponentId, label, mediaItemId, purchasePriceAmount, purchasePriceCurrency, isRetired, notes, installations, maintenanceTasks)
                VALUES (:gearComponentId, :label, :mediaItemId, :purchasePriceAmount, :purchasePriceCurrency, :isRetired, :notes, :installations, :maintenanceTasks)';

        $this->connection->executeStatement($sql, [
            'gearComponentId' => (string) $gearComponent->getId(),
            'label' => (string) $gearComponent->getLabel(),
            'mediaItemId' => $gearComponent->getMediaItemId(),
            'purchasePriceAmount' => $gearComponent->getPurchasePrice()?->getAmount(),
            'purchasePriceCurrency' => $gearComponent->getPurchasePrice()?->getCurrency()->getCode(),
            'isRetired' => (int) $gearComponent->isRetired(),
            'notes' => $gearComponent->getNotes(),
            'installations' => Json::encode($gearComponent->getInstallations()),
            'maintenanceTasks' => Json::encode($gearComponent->getMaintenanceTasks()),
        ]);
        $this->eventBus->publishEvents($gearComponent->getRecordedEvents());
    }

    public function update(GearComponent $gearComponent): void
    {
        $sql = 'UPDATE GearComponent SET
                    label = :label,
                    mediaItemId = :mediaItemId,
                    purchasePriceAmount = :purchasePriceAmount,
                    purchasePriceCurrency = :purchasePriceCurrency,
                    isRetired = :isRetired,
                    notes = :notes,
                    installations = :installations,
                    maintenanceTasks = :maintenanceTasks
                WHERE gearComponentId = :gearComponentId';

        $this->connection->executeStatement($sql, [
            'gearComponentId' => (string) $gearComponent->getId(),
            'label' => (string) $gearComponent->getLabel(),
            'mediaItemId' => $gearComponent->getMediaItemId(),
            'purchasePriceAmount' => $gearComponent->getPurchasePrice()?->getAmount(),
            'purchasePriceCurrency' => $gearComponent->getPurchasePrice()?->getCurrency()->getCode(),
            'isRetired' => (int) $gearComponent->isRetired(),
            'notes' => $gearComponent->getNotes(),
            'installations' => Json::encode($gearComponent->getInstallations()),
            'maintenanceTasks' => Json::encode($gearComponent->getMaintenanceTasks()),
        ]);
        $this->eventBus->publishEvents($gearComponent->getRecordedEvents());
    }

    public function delete(GearComponent $gearComponent): void
    {
        $this->connection->executeStatement(
            'DELETE FROM GearComponent WHERE gearComponentId = :gearComponentId',
            ['gearComponentId' => (string) $gearComponent->getId()],
        );
        $this->eventBus->publishEvents($gearComponent->getRecordedEvents());
    }

    public function find(GearComponentId $gearComponentId): GearComponent
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM GearComponent WHERE gearComponentId = :gearComponentId',
            ['gearComponentId' => (string) $gearComponentId],
        )->fetchAssociative();

        if (false === $result) {
            throw new EntityNotFound(sprintf('GearComponent "%s" not found', $gearComponentId));
        }

        return $this->hydrate(
            result: $result,
            mediaItem: $this->mediaItemRepository->findOptional(MediaItemId::fromOptionalString($result['mediaItemId'])),
        );
    }

    public function findAll(): GearComponents
    {
        $results = $this->connection->executeQuery('SELECT * FROM GearComponent ORDER BY label ASC')->fetchAllAssociative();

        $mediaItemIds = MediaItemIds::fromArray(array_map(
            MediaItemId::fromString(...),
            array_values(array_filter(array_column($results, 'mediaItemId'))),
        ));
        $mediaItemsById = $this->mediaItemRepository->findByIds($mediaItemIds)->keyById();

        return GearComponents::fromArray(array_map(
            fn (array $result): GearComponent => $this->hydrate(
                result: $result,
                mediaItem: $mediaItemsById[$result['mediaItemId'] ?? ''] ?? null,
            ),
            $results,
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result, ?MediaItem $mediaItem): GearComponent
    {
        return GearComponent::fromArray([
            'id' => $result['gearComponentId'],
            'label' => $result['label'],
            'mediaItemId' => $result['mediaItemId'],
            'purchasePrice' => null === $result['purchasePriceAmount'] ? null : [
                'amountInCents' => (int) $result['purchasePriceAmount'],
                'currency' => $result['purchasePriceCurrency'],
            ],
            'isRetired' => (bool) $result['isRetired'],
            'notes' => $result['notes'],
            'installations' => Json::decode($result['installations']),
            'maintenance' => Json::decode($result['maintenanceTasks']),
        ])->withMediaItem($mediaItem);
    }
}
