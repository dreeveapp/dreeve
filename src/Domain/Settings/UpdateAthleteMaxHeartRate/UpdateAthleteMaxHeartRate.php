<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteMaxHeartRate;

use App\Domain\Athlete\MaxHeartRate\MaxHeartRateFormulas;
use App\Domain\Settings\AthleteSettingsPayload;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;

/**
 * Sets how the athlete's max heart rate is determined, either a named formula
 * or measured values that apply from a given date onwards.
 *
 * Max heart rate drives the heart rate zones every activity is scored against,
 * hence #[RequiresRebuild].
 */
#[RequiresRebuild]
final readonly class UpdateAthleteMaxHeartRate extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;
    public const string TYPE_FORMULA = 'formula';
    public const string TYPE_MEASURED = 'measured';

    /**
     * @param string|array<string, int> $formula a named formula, or a date => bpm map
     */
    private function __construct(
        private string|array $formula,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $type = $payload['type'] ?? null;
        if (self::TYPE_FORMULA !== $type && self::TYPE_MEASURED !== $type) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('A valid "type" is required, one of: %s, %s.', self::TYPE_FORMULA, self::TYPE_MEASURED));
        }

        $formula = self::TYPE_FORMULA === $type
            ? self::namedFormulaFromPayload($payload)
            : self::measuredValuesFromPayload($payload);

        try {
            // Validate by construction, exactly as the settings form does.
            new MaxHeartRateFormulas()->determineFormula($formula);
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
            // Reuse the settings form's normalizer, so the API and the admin
            // panel agree on shape, coercion and duplicate-date handling.
            $normalized = AthleteSettingsPayload::normalize([
                'maxHeartRateFormula' => 'dateRangeBased',
                'maxHeartRateFormulaRanges' => array_values($entries),
            ]);
        } catch (\RuntimeException $e) {
            throw CouldNotDeserializeCommand::invalidPayload($e->getMessage());
        }

        /** @var array<string, int> $ranges */
        $ranges = $normalized['maxHeartRateFormula'];

        return $ranges;
    }

    /**
     * @return string|array<string, int>
     */
    public function getFormula(): string|array
    {
        return $this->formula;
    }
}
