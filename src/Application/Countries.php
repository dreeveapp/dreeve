<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Activity\Route\RouteGeography;
use Doctrine\DBAL\Connection;
use Symfony\Component\Intl\Countries as SymfonyCountries;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class Countries
{
    private const string PASSED_THROUGH_COUNTRIES_JSON_PATH = '$.'.RouteGeography::PASSED_TROUGH_COUNTRIES;

    /** @var string[] */
    private array $countriesKeyedByAlpha2codes;

    public function __construct(
        private Connection $connection,
        private LocaleSwitcher $localeSwitcher,
    ) {
        $this->countriesKeyedByAlpha2codes = SymfonyCountries::getNames();
    }

    /**
     * @return array<string, string>
     */
    public function getUsedInActivities(): array
    {
        $passedThroughCountries = self::PASSED_THROUGH_COUNTRIES_JSON_PATH;

        $results = $this->connection->executeQuery(
            <<<SQL
            SELECT DISTINCT countryCode
            FROM (
                 SELECT JSON_EXTRACT(routeGeography, '$.country_code') AS countryCode
                 FROM Activity

                 UNION ALL

                 SELECT value AS countryCode
                 FROM Activity,
                     json_each(JSON_EXTRACT(routeGeography, '{$passedThroughCountries}'))
                 )
            WHERE countryCode IS NOT NULL
            SQL
        )->fetchFirstColumn();

        return $this->hydrate($results);
    }

    /**
     * @return array<string, string>
     */
    public function getUsedInPhotos(): array
    {
        $passedThroughCountries = self::PASSED_THROUGH_COUNTRIES_JSON_PATH;

        $results = $this->connection->executeQuery(
            <<<SQL
            SELECT DISTINCT countryCode
            FROM (
                 SELECT JSON_EXTRACT(routeGeography, '$.country_code') AS countryCode
                 FROM Activity WHERE totalImageCount > 0

                 UNION ALL

                 SELECT value AS countryCode
                 FROM Activity,
                     JSON_EACH(JSON_EXTRACT(routeGeography, '{$passedThroughCountries}'))
                 WHERE totalImageCount > 0
                 )
            WHERE countryCode IS NOT NULL
            SQL
        )->fetchFirstColumn();

        return $this->hydrate($results);
    }

    /**
     * @return array<string, string>
     */
    public function getUsedInSegments(): array
    {
        $results = $this->connection->executeQuery(
            <<<SQL
            SELECT DISTINCT countryCode
            FROM Segment
            WHERE countryCode IS NOT NULL
            SQL
        )->fetchFirstColumn();

        return $this->hydrate($results);
    }

    public function findCountryCodeByCountryName(string $countryName): ?string
    {
        if (!$countryCode = array_search($countryName, $this->countriesKeyedByAlpha2codes)) {
            return null;
        }

        return $countryCode;
    }

    /**
     * @param string[] $results
     *
     * @return array<string, string>
     */
    private function hydrate(array $results): array
    {
        $countries = [];
        foreach ($results as $countryCode) {
            $countryCode = strtolower($countryCode);
            if ('xk' === $countryCode) {
                // Currently Symfony does not support Kosovo as a country.
                // Need to wait until this commit is released in Symfony 7.4:
                // https://github.com/symfony/symfony/issues/40020
                $countries[$countryCode] = 'Kosovo';
                continue;
            }
            $countries[$countryCode] = SymfonyCountries::getName(
                country: strtoupper($countryCode),
                displayLocale: $this->localeSwitcher->getLocale()
            );
        }

        return $countries;
    }
}
