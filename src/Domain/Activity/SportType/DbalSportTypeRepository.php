<?php

declare(strict_types=1);

namespace App\Domain\Activity\SportType;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Repository\DbalRepository;
use Doctrine\DBAL\Connection;

final readonly class DbalSportTypeRepository extends DbalRepository implements SportTypeRepository
{
    public function __construct(
        Connection $connection,
        private SettingsRepository $settingsRepository,
    ) {
        parent::__construct($connection);
    }

    public function findAll(): SportTypes
    {
        return SportTypes::fromArray(array_map(
            SportType::from(...),
            $this->connection->executeQuery(
                sprintf('SELECT DISTINCT sportType FROM Activity ORDER BY CASE sportType %s END', $this->buildOrderByStatement())
            )->fetchFirstColumn()
        ));
    }

    public function findForImages(): SportTypes
    {
        $notInSportTypes = ['invalid'];
        foreach ($this->settingsRepository->appearance()->getHidePhotosForSportTypes() as $sportType) {
            $notInSportTypes[] = $sportType->value;
        }

        return SportTypes::fromArray(array_map(
            SportType::from(...),
            $this->connection->executeQuery(
                sprintf(
                    'SELECT DISTINCT sportType FROM Activity WHERE totalImageCount > 0 AND sportType NOT IN (%s) ORDER BY CASE sportType %s END',
                    "'".implode("','", $notInSportTypes)."'",
                    $this->buildOrderByStatement()
                )
            )->fetchFirstColumn()
        ));
    }

    private function buildOrderByStatement(): string
    {
        $orderByStatement = [];
        foreach ($this->settingsRepository->appearance()->getSportTypesSortingOrder() as $index => $sportType) {
            $orderByStatement[] = sprintf('WHEN "%s" THEN %d', $sportType->value, $index);
        }
        $orderByStatement[] = 'ELSE 9999';

        return implode(' ', $orderByStatement);
    }
}
