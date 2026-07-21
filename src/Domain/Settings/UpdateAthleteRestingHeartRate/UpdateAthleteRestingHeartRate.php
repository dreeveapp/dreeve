<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteRestingHeartRate;

use App\Domain\Athlete\RestingHeartRate\RestingHeartRateFormulas;
use App\Domain\Settings\AthleteSettingsPayload;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;

/**
 * Sets how the athlete's resting heart rate is determined: the age-based
 * heuristic, one fixed value, or measured values applying from a given date.
 *
 * Resting heart rate feeds heart rate reserve calculations, hence
 * #[RequiresRebuild].
 */
#[RequiresRebuild]
final readonly class UpdateAthleteRestingHeartRate extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;
    public const string TYPE_FORMULA = 'formula';
    public const string TYPE_FIXED = 'fixed';
    public const string TYPE_MEASURED = 'measured';

    /**
     * @param string|int|array<string, int> $formula a named formula, a fixed bpm, or a date => bpm map
     */
    private function __construct(
        private string|int|array $formula,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $type = $payload['type'] ?? null;

        $formula = match ($type) {
            self::TYPE_FORMULA => self::namedFormulaFromPayload($payload),
            self::TYPE_FIXED => self::fixedValueFromPayload($payload),
            self::TYPE_MEASURED => self::measuredValuesFromPayload($payload),
            default => throw CouldNotDeserializeCommand::invalidPayload(sprintf('A valid "type" is required, one of: %s, %s, %s.', self::TYPE_FORMULA, self::TYPE_FIXED, self::TYPE_MEASURED)),
        };

        try {
            // Validate by construction, exactly as the settings form does.
            new RestingHeartRateFormulas()->determineFormula($formula);
        } catch (\RuntimeException $e) {
            throw CouldNotDeserializeCommand::invalidPayload($e->getMessage());
        }

        return new self(
            formula: $formula
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function namedFormulaFromPayload(array $payload): string
    {
        $formula = $payload['formula'] ?? null;
        if (!is_string($formula) || '' === trim($formula)) {
            throw CouldNotDeserializeCommand::invalidPayload('"formula" is required when type is "formula".');
        }

        return trim($formula);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function fixedValueFromPayload(array $payload): int
    {
        try {
            // Reuse the settings form's normalizer so the API and the admin
            // panel agree on coercion and bounds.
            $normalized = AthleteSettingsPayload::normalize([
                'restingHeartRateFormula' => 'fixed',
                'restingHeartRateFormulaFixedValue' => $payload['bpm'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            throw CouldNotDeserializeCommand::invalidPayload($e->getMessage());
        }

        /** @var int $bpm */
        $bpm = $normalized['restingHeartRateFormula'];

        return $bpm;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, int>
     */
    private static function measuredValuesFromPayload(array $payload): array
    {
        $entries = $payload['entries'] ?? null;
        if (!is_array($entries) || [] === $entries) {
            throw CouldNotDeserializeCommand::invalidPayload('A non-empty "entries" array is required when type is "measured".');
        }

        try {
            $normalized = AthleteSettingsPayload::normalize([
                'restingHeartRateFormula' => 'dateRangeBased',
                'restingHeartRateFormulaRanges' => array_values($entries),
            ]);
        } catch (\RuntimeException $e) {
            throw CouldNotDeserializeCommand::invalidPayload($e->getMessage());
        }

        /** @var array<string, int> $ranges */
        $ranges = $normalized['restingHeartRateFormula'];

        return $ranges;
    }

    /**
     * @return string|int|array<string, int>
     */
    public function getFormula(): string|int|array
    {
        return $this->formula;
    }
}
