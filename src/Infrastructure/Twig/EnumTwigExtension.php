<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Config\Leaflet\BasemapPreset;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class EnumTwigExtension
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[AsTwigFunction('sportTypeOptions')]
    public function getSportTypeOptions(): array
    {
        return array_map(
            fn (SportType $sportType): array => [
                'value' => $sportType->value,
                'label' => $sportType->trans($this->translator),
            ],
            SportType::cases(),
        );
    }

    #[AsTwigFunction('activityTypeFrom')]
    public function getActivityTypeFrom(string $activityType): ActivityType
    {
        return ActivityType::from($activityType);
    }

    #[AsTwigFunction('basemapPresetForUrls')]
    public function getBasemapPresetForUrls(mixed $tileLayerUrls): ?BasemapPreset
    {
        if (!is_array($tileLayerUrls)) {
            return null;
        }

        /** @var string[] $urls */
        $urls = array_values(array_filter($tileLayerUrls, is_string(...)));

        return BasemapPreset::tryFromTileLayerUrls($urls);
    }

    /**
     * @return array<string, string[]>
     */
    #[AsTwigFunction('enumTileLayerUrls')]
    public function getEnumTileLayerUrls(): array
    {
        return BasemapPreset::tileLayerUrlsIndexedByPreset();
    }
}
