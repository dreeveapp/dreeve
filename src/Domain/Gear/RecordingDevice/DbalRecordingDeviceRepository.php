<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Repository\DbalRepository;
use Money\Currency;
use Money\Money;

final readonly class DbalRecordingDeviceRepository extends DbalRepository implements RecordingDeviceRepository
{
    public function findAll(): RecordingDevices
    {
        $results = $this->connection->executeQuery(
            'SELECT Activity.deviceName,
                    SUM(Activity.movingTimeInSeconds) as totalMovingTime,
                    SUM(Activity.distance) as totalDistance,
                    SUM(Activity.elevation) as totalElevation,
                    COUNT(*) as activityCount,
                    RecordingDevice.id,
                    RecordingDevice.purchasePriceAmount,
                    RecordingDevice.purchasePriceCurrency
             FROM Activity
             LEFT JOIN RecordingDevice ON RecordingDevice.name = Activity.deviceName
             WHERE Activity.deviceName IS NOT NULL
             GROUP BY Activity.deviceName
             ORDER BY activityCount DESC'
        )->fetchAllAssociative();

        return RecordingDevices::fromArray(array_map(
            function (array $result): RecordingDevice {
                $purchasePrice = null;
                $purchasePriceCurrency = (string) ($result['purchasePriceCurrency'] ?? '');
                if (isset($result['purchasePriceAmount']) && '' !== $purchasePriceCurrency) {
                    $purchasePrice = new Money(
                        amount: (int) $result['purchasePriceAmount'],
                        currency: new Currency($purchasePriceCurrency)
                    );
                }

                return RecordingDevice::fromState(
                    // Devices discovered from activities that have no persisted row yet
                    // fall back to the id derived from their name.
                    id: RecordingDeviceId::fromOptionalString($result['id']) ?? RecordingDeviceId::fromName($result['deviceName']),
                    name: $result['deviceName'],
                    timeTracked: Seconds::from((float) $result['totalMovingTime']),
                    distanceTracked: Meter::from((float) $result['totalDistance'])->toKilometer(),
                    elevationTracked: Meter::from((float) $result['totalElevation']),
                    activityCount: (int) $result['activityCount'],
                    purchasePrice: $purchasePrice,
                );
            },
            $results,
        ));
    }

    public function find(RecordingDeviceId $recordingDeviceId): RecordingDevice
    {
        $recordingDevice = $this->findAll()->find(
            static fn (RecordingDevice $recordingDevice): bool => (string) $recordingDevice->getId() === (string) $recordingDeviceId
        );

        if (!$recordingDevice instanceof RecordingDevice) {
            throw new EntityNotFound(sprintf('RecordingDevice "%s" not found', $recordingDeviceId));
        }

        return $recordingDevice;
    }

    public function save(RecordingDevice $recordingDevice): void
    {
        $purchasePrice = $recordingDevice->getPurchasePrice();
        $this->connection->executeStatement(
            'INSERT INTO RecordingDevice (id, name, purchasePriceAmount, purchasePriceCurrency)
             VALUES (:id, :name, :purchasePriceAmount, :purchasePriceCurrency)
             ON CONFLICT (id) DO UPDATE SET
                name = excluded.name,
                purchasePriceAmount = excluded.purchasePriceAmount,
                purchasePriceCurrency = excluded.purchasePriceCurrency',
            [
                'id' => $recordingDevice->getId(),
                'name' => $recordingDevice->getName(),
                'purchasePriceAmount' => $purchasePrice?->getAmount(),
                'purchasePriceCurrency' => $purchasePrice?->getCurrency()->getCode(),
            ]
        );
    }
}
