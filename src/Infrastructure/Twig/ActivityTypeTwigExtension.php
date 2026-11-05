<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\Gear;
use App\Domain\Gear\Gears;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class ActivityTypeTwigExtension
{
    public function __construct(
        private TranslatorInterface $translator,
        private ActivityTypeRepository $activityTypeRepository,
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

    /**
     * @param iterable<SportType> $sportTypes
     *
     * @return list<array{activityType: ActivityType, sportTypes: list<SportType>}>
     */
    #[AsTwigFunction('sportTypesGroupedByActivityType')]
    public function groupSportTypesByActivityType(iterable $sportTypes): array
    {
        $groups = [];
        foreach ($sportTypes as $sportType) {
            $activityType = $sportType->getActivityType();
            $groups[$activityType->value] ??= ['activityType' => $activityType, 'sportTypes' => []];
            $groups[$activityType->value]['sportTypes'][] = $sportType;
        }

        return array_values($groups);
    }

    /**
     * @return list<array{label: TranslatableInterface, gears: Gears}>
     */
    #[AsTwigFunction('gearsGroupedByActivityType')]
    public function groupGearsByActivityType(Gears $gears, ?ActivityType $suggestFor = null): array
    {
        $activeGears = $gears->filter(fn (Gear $gear): bool => !$gear->isRetired());
        $suggestedGears = $suggestFor instanceof ActivityType
            ? $activeGears->filter(fn (Gear $gear): bool => $gear->getActivityTypes()->has($suggestFor))
            : Gears::empty();
        $otherGears = $activeGears->filter(fn (Gear $gear): bool => !$suggestedGears->has($gear));

        $gearGroups = [['label' => new TranslatableMessage('Suggested', domain: 'admin'), 'gears' => $suggestedGears]];
        foreach ($this->activityTypeRepository->findAll() as $activityType) {
            $gearGroups[] = [
                'label' => $activityType,
                'gears' => $otherGears->filter(fn (Gear $gear): bool => $activityType === $gear->getMainActivityType()),
            ];
        }
        $gearGroups[] = [
            'label' => new TranslatableMessage('Unused gear', domain: 'admin'),
            'gears' => $otherGears->filter(fn (Gear $gear): bool => null === $gear->getMainActivityType()),
        ];
        $gearGroups[] = [
            'label' => new TranslatableMessage('Retired', domain: 'admin'),
            'gears' => $gears->filter(fn (Gear $gear): bool => $gear->isRetired()),
        ];

        return array_values(array_filter(
            $gearGroups,
            fn (array $gearGroup): bool => !$gearGroup['gears']->isEmpty(),
        ));
    }

    #[AsTwigFunction('activityTypeFrom')]
    public function getActivityTypeFrom(string $activityType): ActivityType
    {
        return ActivityType::from($activityType);
    }
}
