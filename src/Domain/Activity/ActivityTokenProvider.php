<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Tokenizer\Token;
use App\Infrastructure\Tokenizer\TokenDefinition;
use App\Infrastructure\Tokenizer\TokenizerContext;
use App\Infrastructure\Tokenizer\TokenProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityTokenProvider implements TokenProvider
{
    use ProvideMeasurementFormats;

    private const string PREFIX = 'activity';

    public function __construct(
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
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
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The current name of the activity', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'workout-type',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The workout type', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'start-date',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The start date, optionally with a custom PHP date format', domain: 'admin', locale: $locale),
                supportsModifier: true,
                exampleModifier: 'd-m-Y',
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'distance',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The distance', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'elevation',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The elevation gain', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'moving-time',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The moving time', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'elapsed-time',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The elapsed time', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'average-speed',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The average speed', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'max-speed',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The max speed', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'average-heart-rate',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The average heart rate', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'max-heart-rate',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The max heart rate', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'average-power',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The average power', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'max-power',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The max power', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'average-cadence',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The average cadence', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'calories',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The calories burned', domain: 'admin', locale: $locale),
            ),
            TokenDefinition::create(
                prefix: self::PREFIX,
                key: 'device-name',
                description: static fn (TranslatorInterface $translator, ?string $locale): string => $translator->trans('The device used to record the activity', domain: 'admin', locale: $locale),
            ),
        ];
    }

    public function resolve(Token $token, TokenizerContext $context): ?string
    {
        if (!($activity = $context->get(Activity::class)) instanceof Activity) {
            return null;
        }

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        return match ($token->getKey()) {
            'name' => $activity->getName(),
            'workout-type' => $activity->getWorkoutType()?->trans($this->translator),
            'start-date' => $activity->getStartDate()->translatedFormat(
                $token->getModifier() ?? (string) $this->settingsRepository->appearance()->getDateAndTimeFormat()->getDateFormatShort()
            ),
            'distance' => $this->formatUnitWithSymbol($activity->getDistance()->toUnitSystem($unitSystem), precision: 1),
            'elevation' => $this->formatUnitWithSymbol($activity->getElevation()->toUnitSystem($unitSystem), precision: 0),
            'moving-time' => $activity->getMovingTimeFormatted(),
            'elapsed-time' => $activity->getElapsedTimeFormatted(),
            'average-speed' => $this->formatUnitWithSymbol($activity->getAverageSpeed()->toUnitSystem($unitSystem), precision: 1),
            'max-speed' => $this->formatUnitWithSymbol($activity->getMaxSpeed()->toUnitSystem($unitSystem), precision: 1),
            'average-heart-rate' => null !== $activity->getAverageHeartRate() ? (string) $activity->getAverageHeartRate() : null,
            'max-heart-rate' => null !== $activity->getMaxHeartRate() ? (string) $activity->getMaxHeartRate() : null,
            'average-power' => null !== $activity->getAveragePower() ? (string) $activity->getAveragePower() : null,
            'max-power' => null !== $activity->getMaxPower() ? (string) $activity->getMaxPower() : null,
            'average-cadence' => null !== $activity->getAverageCadence() ? (string) $activity->getAverageCadence() : null,
            'calories' => null !== $activity->getCalories() ? (string) $activity->getCalories() : null,
            'device-name' => $activity->getDeviceName(),
            default => null,
        };
    }
}
