<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Time\Format\TimeFormat;
use App\Infrastructure\Twig\FormatDateAndTimeTwigExtension;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FormatDateAndTimeTwigExtensionTest extends ContainerTestCase
{
    #[DataProvider(methodName: 'provideDates')]
    public function testFormatDate(string $expectedFormattedDateString, SerializableDateTime $date, string $formatType, string $shortDateFormat, string $normalDateFormat): void
    {
        $extension = $this->extensionFor($shortDateFormat, $normalDateFormat, TimeFormat::AM_PM);

        $this->assertEquals(
            $expectedFormattedDateString,
            $extension->formatDate($date, $formatType)
        );
    }

    public function testFormatDateItShouldThrow(): void
    {
        $extension = $this->extensionFor('d-m-y', 'd-m-Y', TimeFormat::AM_PM);

        $this->expectExceptionObject(new \InvalidArgumentException('Invalid date formatType "invalid"'));
        $extension->formatDate(SerializableDateTime::fromString('2025-01-01'), 'invalid');
    }

    #[DataProvider(methodName: 'provideTimes')]
    public function testFormatTime(string $expectedFormattedTimeString, SerializableDateTime $date, TimeFormat $timeFormat): void
    {
        $extension = $this->extensionFor('d-m-y', 'd-m-Y', $timeFormat);

        $this->assertEquals(
            $expectedFormattedTimeString,
            $extension->formatTime($date)
        );
    }

    private function extensionFor(string $shortDateFormat, string $normalDateFormat, TimeFormat $timeFormat): FormatDateAndTimeTwigExtension
    {
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::APPEARANCE, [
            'dateFormat' => [
                'short' => $shortDateFormat,
                'normal' => $normalDateFormat,
            ],
            'timeFormat' => $timeFormat->value,
        ]);

        return new FormatDateAndTimeTwigExtension($settingsRepository);
    }

    public static function provideDates(): array
    {
        return [
            ['31-01-25', SerializableDateTime::fromString('31-01-2025 15:30'), 'short', 'd-m-y', 'd-m-Y'],
            ['31-01-2025', SerializableDateTime::fromString('31-01-2025 15:30'), 'normal', 'd-m-y', 'd-m-Y'],
            ['01-31-25', SerializableDateTime::fromString('31-01-2025 15:30'), 'short', 'm-d-y', 'm-d-Y'],
            ['01-31-2025', SerializableDateTime::fromString('31-01-2025 15:30'), 'normal', 'm-d-y', 'm-d-Y'],
            ['Fri., 31.01.25', SerializableDateTime::fromString('31-01-2025 15:30'), 'normal', 'D., d.m.y', 'D., d.m.y'],
        ];
    }

    public static function provideTimes(): array
    {
        return [
            ['23:53', SerializableDateTime::fromString('31-01-2025 23:53'), TimeFormat::TWENTY_FOUR],
            ['11:53 pm', SerializableDateTime::fromString('31-01-2025 23:53'), TimeFormat::AM_PM],
            ['11:53 am', SerializableDateTime::fromString('31-01-2025 11:53'), TimeFormat::AM_PM],
        ];
    }
}
