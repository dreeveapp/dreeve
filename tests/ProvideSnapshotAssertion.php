<?php

declare(strict_types=1);

namespace App\Tests;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\CompressedString;
use Doctrine\DBAL\Connection;

trait ProvideSnapshotAssertion
{
    abstract protected function getConnection(): Connection;

    abstract public function assertMatchesJsonSnapshot(mixed $actual): void;

    /**
     * @param array<string, mixed> $params
     * @param list<string>         $compressedColumns
     */
    protected function assertCompressedDatabaseQueryMatchesSnapshot(
        string $sql,
        array $params = [],
        array $compressedColumns = ['data'],
    ): void {
        $results = $this->getConnection()
            ->executeQuery($sql, $params)->fetchAllAssociative();

        foreach ($results as &$result) {
            foreach ($compressedColumns as $column) {
                $result[$column] = null !== $result[$column]
                    ? CompressedString::fromCompressed($result[$column])->uncompress()
                    : null;
            }
        }

        $this->assertMatchesJsonSnapshot(
            Json::encode($results)
        );
    }
}
