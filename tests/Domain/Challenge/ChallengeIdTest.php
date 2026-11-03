<?php

namespace App\Tests\Domain\Challenge;

use App\Domain\Challenge\ChallengeId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChallengeIdTest extends TestCase
{
    #[DataProvider('provideData')]
    public function testFromDateAndName(SerializableDateTime $date, string $name, ChallengeId $expectedChallengeId): void
    {
        $this->assertEquals(
            $expectedChallengeId,
            ChallengeId::fromDateAndName($date, $name),
        );
    }

    #[DataProvider('provideDataOldVersion')]
    public function testToOldVersion(SerializableDateTime $date, string $name, ChallengeId $expectedChallengeId): void
    {
        $this->assertEquals(
            $expectedChallengeId,
            ChallengeId::toOldVersion($date, $name),
        );
    }

    public function testItDecodesEntities(): void
    {
        $this->assertEquals(
            ChallengeId::fromDateAndName(
                createdOn: SerializableDateTime::fromString('2026-01-01'),
                name: 'roc_d&#x27;azur_cic_challenge_2024'
            ),
            ChallengeId::fromDateAndName(
                createdOn: SerializableDateTime::fromString('2026-01-01'),
                name: 'roc_d&#39;azur_cic_challenge_2024'
            )
        );
    }

    /**
     * @return list<array{SerializableDateTime, string, ChallengeId}>
     */
    public static function provideData(): array
    {
        return [
            [
                SerializableDateTime::fromString('2022-10-23'),
                'Short name with spaces',
                ChallengeId::fromUnprefixed('2022-10_short_name_with_spaces'),
            ],
            [
                SerializableDateTime::fromString('2023-01-23'),
                str_repeat('r', 300),
                ChallengeId::fromUnprefixed('2023-01_'.str_repeat('r', 250)),
            ],
            [
                SerializableDateTime::fromString('2022-10-23'),
                'roc_d&#x27;azur_cic_challenge_2024',
                ChallengeId::fromUnprefixed('2022-10_roc_d_azur_cic_challenge_2024'),
            ],
            [
                SerializableDateTime::fromString('2022-10-23'),
                'roc_d&#39;azur_cic_challenge_2024',
                ChallengeId::fromUnprefixed('2022-10_roc_d_azur_cic_challenge_2024'),
            ],
        ];
    }

    /**
     * @return list<array{SerializableDateTime, string, ChallengeId}>
     */
    public static function provideDataOldVersion(): array
    {
        return [
            [
                SerializableDateTime::fromString('2022-10-23'),
                'roc_d&#x27;azur_cic_challenge_2024',
                ChallengeId::fromUnprefixed('2022-10_roc_d&#x27;azur_cic_challenge_2024'),
            ],
            [
                SerializableDateTime::fromString('2022-10-23'),
                'roc_d&#39;azur_cic_challenge_2024',
                ChallengeId::fromUnprefixed('2022-10_roc_d&#39;azur_cic_challenge_2024'),
            ],
        ];
    }
}
