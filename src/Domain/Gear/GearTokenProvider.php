<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Activity\Activity;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Tokenizer\Token;
use App\Infrastructure\Tokenizer\TokenDefinition;
use App\Infrastructure\Tokenizer\TokenizerContext;
use App\Infrastructure\Tokenizer\TokenProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class GearTokenProvider implements TokenProvider
{
    use ProvideMeasurementFormats;

    private const string PREFIX = 'gear';

    public function __construct(
        private GearRepository $gearRepository,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getPrefix(): string
    {
        return self::PREFIX;
    }

    public function getTokenDefinitions(): array
    {
        return [
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'name',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The name of the gear assigned to the activity', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'distance',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The total distance ridden with the gear', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'number-of-activities',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The number of activities recorded with the gear', domain: 'admin', locale: $locale),
            ),
        ];
    }

    public function resolve(Token $token, TokenizerContext $context): ?string
    {
        if (!($activity = $context->get(Activity::class)) instanceof Activity) {
            return null;
        }
        if (!$gearId = $activity->getGearId()) {
            return null;
        }

        try {
            $gear = $this->gearRepository->find($gearId);
        } catch (EntityNotFound) {
            return null;
        }

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        return match ($token->getKey()) {
            'name' => $gear->getName(),
            'distance' => $this->formatUnitWithSymbol($gear->getDistance()->toUnitSystem($unitSystem), precision: 0),
            'number-of-activities' => (string) $gear->getNumberOfActivities(),
            default => null,
        };
    }
}
