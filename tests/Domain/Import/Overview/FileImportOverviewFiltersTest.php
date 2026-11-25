<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\Overview;

use App\Domain\Activity\ActivityId;
use App\Domain\Import\Overview\FileImportOverviewFilters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class FileImportOverviewFiltersTest extends TestCase
{
    public function testItIsNotEmptyWhenOnlyOneFilterIsSet(): void
    {
        $this->assertFalse(FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['status' => 'failed'],
        ]))->isEmpty());
        $this->assertFalse(FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['source' => 'fitFile'],
        ]))->isEmpty());
        $this->assertFalse(FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['filename' => 'ride'],
        ]))->isEmpty());
        $this->assertFalse(FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['activity' => 'activity-123'],
        ]))->isEmpty());
    }

    public function testItReadsATrimmedFilename(): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['filename' => '  Morning Ride.fit '],
        ]));

        $this->assertSame('Morning Ride.fit', $filters->getFilename());
    }

    #[DataProvider('provideActivityIds')]
    public function testItResolvesTheActivityId(string $activityId): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['activity' => $activityId],
        ]));

        $this->assertEquals(ActivityId::fromUnprefixed('123'), $filters->getActivityId());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideActivityIds(): iterable
    {
        yield 'a prefixed id, as filled in by the autocomplete' => ['activity-123'];
        yield 'an id surrounded by whitespace' => [' activity-123 '];
    }

    public function testItIgnoresAnActivityIdWithoutPrefix(): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['activity' => '123'],
        ]));

        $this->assertNull($filters->getActivityId());
        $this->assertTrue($filters->isEmpty());
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('provideIgnoredFilterValues')]
    public function testItSilentlyIgnoresUnusableValues(array $query): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request(query: $query));

        $this->assertNull($filters->getStatus());
        $this->assertNull($filters->getSource());
        $this->assertNull($filters->getFilename());
        $this->assertNull($filters->getActivityId());
        $this->assertTrue($filters->isEmpty());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideIgnoredFilterValues(): iterable
    {
        yield 'values that are not backed enum values' => [
            ['filters' => ['status' => 'bogus', 'source' => 'bogus']],
        ];

        yield 'empty strings, as submitted by the "All" options' => [
            ['filters' => ['status' => '', 'source' => '', 'filename' => '', 'activity' => '']],
        ];

        yield 'whitespace-only text values' => [
            ['filters' => ['filename' => '   ', 'activity' => '  ']],
        ];

        yield 'nested arrays instead of scalar values' => [
            ['filters' => ['status' => ['failed'], 'source' => ['fitFile'], 'filename' => ['a.fit'], 'activity' => ['123']]],
        ];

        yield 'unrelated filter names' => [
            ['filters' => ['foo' => 'bar']],
        ];
    }
}
