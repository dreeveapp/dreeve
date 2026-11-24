<?php

declare(strict_types=1);

namespace App\Domain\Activity\Gap;

final readonly class StravaLikeGapAdjustmentModel
{
    /**
     * Empirical GAP distance factors shaped from Schroeder's reverse engineering
     * notes and Strava's public model description. Grades are decimal fractions.
     *
     * @var list<array{float, float}>
     */
    private const array FACTORS_BY_GRADE = [
        [-0.50, 1.35],
        [-0.35, 1.25],
        [-0.25, 1.12],
        [-0.18, 1.01],
        [-0.15, 0.96],
        [-0.10, 0.88],
        [-0.05, 0.94],
        [0.00, 1.00],
        [0.05, 1.20],
        [0.10, 1.42],
        [0.15, 1.70],
        [0.20, 2.00],
        [0.30, 2.60],
        [0.40, 3.25],
        [0.50, 3.90],
    ];

    public static function calculateAdjustmentFactor(float $grade): float
    {
        $grade = clamp($grade, -0.50, 0.50);
        $previousGrade = self::FACTORS_BY_GRADE[0][0];
        $previousFactor = self::FACTORS_BY_GRADE[0][1];

        foreach (self::FACTORS_BY_GRADE as [$knownGrade, $knownFactor]) {
            if (abs($grade - $knownGrade) < 0.0000001) {
                return $knownFactor;
            }

            if ($knownGrade > $grade) {
                $ratio = ($grade - $previousGrade) / ($knownGrade - $previousGrade);

                return $previousFactor + (($knownFactor - $previousFactor) * $ratio);
            }

            $previousGrade = $knownGrade;
            $previousFactor = $knownFactor;
        }

        return $previousFactor; // @codeCoverageIgnore
    }
}
