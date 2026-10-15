<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Activity\Eddington\Config\EddingtonConfiguration;

final readonly class MetricsSettings
{
    private function __construct(
        private EddingtonConfiguration $eddingtonConfiguration,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $eddington = $data['eddington'] ?? [];
        $eddington = array_values(array_map(
            static function (mixed $item): mixed {
                if (!is_array($item)) {
                    return $item; // @codeCoverageIgnore
                }
                $item['showInDashboardWidget'] = filter_var($item['showInDashboardWidget'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $item['sportTypesToInclude'] = array_values(is_array($item['sportTypesToInclude'] ?? null) ? $item['sportTypesToInclude'] : []);

                return $item;
            },
            is_array($eddington) ? $eddington : []
        ));

        return new self(
            eddingtonConfiguration: EddingtonConfiguration::fromScalarArray($eddington),
        );
    }

    public function getEddingtonConfiguration(): EddingtonConfiguration
    {
        return $this->eddingtonConfiguration;
    }
}
