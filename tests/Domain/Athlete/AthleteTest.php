<?php

namespace App\Tests\Domain\Athlete;

use App\Domain\Athlete\Athlete;
use App\Domain\Athlete\MaxHeartRate\Fox;
use App\Domain\Athlete\RestingHeartRate\HeuristicAgeBased;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AthleteTest extends TestCase
{
    #[DataProvider(methodName: 'provideDataAthleteAgeData')]
    public function testGetAthleteAge(
        SerializableDateTime $on,
        SerializableDateTime $athleteBirthday,
        int $expectedAge): void
    {
        $athlete = Athlete::create(
            athleteId: 'athlete-1',
            birthDate: $athleteBirthday,
            firstName: 'Robin',
            lastName: 'Ingelbrecht',
            gender: 'M',
            maxHeartRateFormula: new Fox(),
            restingHeartRateFormula: new HeuristicAgeBased(),
        );

        $this->assertEquals(
            $expectedAge,
            $athlete->getAgeInYears($on)
        );
    }

    #[DataProvider(methodName: 'provideBlankNameData')]
    public function testItFallsBackToAPlaceholderName(
        ?string $firstName,
        ?string $lastName,
        ?string $gender,
        string $expectedName,
        string $expectedFirstLetter,
        bool $expectedIsMale): void
    {
        $athlete = Athlete::create(
            athleteId: 'athlete-1',
            birthDate: SerializableDateTime::fromString('1989-08-14'),
            firstName: $firstName,
            lastName: $lastName,
            gender: $gender,
            maxHeartRateFormula: new Fox(),
            restingHeartRateFormula: new HeuristicAgeBased(),
        );

        $this->assertSame($expectedName, (string) $athlete->getName());
        $this->assertSame($expectedFirstLetter, $athlete->getFirstLetterOfFirstName());
        $this->assertSame($expectedIsMale, $athlete->isMale());
    }

    public static function provideBlankNameData(): array
    {
        return [
            'configured' => ['Robin', 'Ingelbrecht', 'F', 'Robin Ingelbrecht', 'R', false],
            'null' => [null, null, null, 'John Doe', 'J', true],
            'empty strings' => ['', '', '', 'John Doe', 'J', true],
            'whitespace only' => ['   ', "\t", ' ', 'John Doe', 'J', true],
            'only a first name' => ['Robin', '', 'M', 'Robin Doe', 'R', true],
            'only a last name' => ['', 'Ingelbrecht', 'M', 'John Ingelbrecht', 'J', true],
            'padded' => ['  Robin  ', '  Ingelbrecht  ', '  F  ', 'Robin Ingelbrecht', 'R', false],
        ];
    }

    public static function provideDataAthleteAgeData(): array
    {
        return [
            [SerializableDateTime::fromString('2023-08-13'), SerializableDateTime::fromString('1989-08-14'), 33],
            [SerializableDateTime::fromString('2023-08-14'), SerializableDateTime::fromString('1989-08-14'), 34],
            [SerializableDateTime::fromString('2023-08-15'), SerializableDateTime::fromString('1989-08-14'), 34],
        ];
    }
}
