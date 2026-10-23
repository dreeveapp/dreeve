<?php

declare(strict_types=1);

namespace App\Domain\Zwift;

use App\Domain\Activity\WorldType;
use Doctrine\DBAL\Connection;

final readonly class ZwiftWorlds
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return ZwiftMap[]
     */
    public function getUsedInActivities(): array
    {
        $zwiftWorldIds = $this->connection->executeQuery(
            <<<SQL
            SELECT DISTINCT JSON_EXTRACT(routeGeography, '$.state')
            FROM Activity
            WHERE worldType = :worldType AND JSON_EXTRACT(routeGeography, '$.state') IS NOT NULL
            SQL,
            [
                'worldType' => WorldType::ZWIFT->value,
            ]
        )->fetchFirstColumn();

        $zwiftWorlds = array_map(ZwiftMap::fromId(...), $zwiftWorldIds);
        usort($zwiftWorlds, static fn (ZwiftMap $a, ZwiftMap $b): int => strcmp($a->getLabel(), $b->getLabel()));

        return $zwiftWorlds;
    }
}
