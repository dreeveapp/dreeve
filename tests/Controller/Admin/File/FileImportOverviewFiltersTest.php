<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin\File;

use App\Controller\Admin\File\FileImportOverviewFilters;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class FileImportOverviewFiltersTest extends TestCase
{
    public function testItReturnsNullWhenNoFiltersAreGiven(): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request());

        $this->assertNull($filters->getStatus());
        $this->assertNull($filters->getSource());
        $this->assertTrue($filters->isEmpty());
    }

    public function testItReadsStatusAndSourceFromTheNestedQueryParam(): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['status' => 'failed', 'source' => 'fitFile'],
        ]));

        $this->assertEquals(FileImportStatus::FAILED, $filters->getStatus());
        $this->assertEquals(ImportSource::FIT_FILE, $filters->getSource());
        $this->assertFalse($filters->isEmpty());
    }

    public function testItIsNotEmptyWhenOnlyOneFilterIsSet(): void
    {
        $this->assertFalse(FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['status' => 'failed'],
        ]))->isEmpty());
        $this->assertFalse(FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['source' => 'fitFile'],
        ]))->isEmpty());
    }

    #[DataProvider('provideStatuses')]
    public function testItResolvesEveryStatus(FileImportStatus $status): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['status' => $status->value],
        ]));

        $this->assertEquals($status, $filters->getStatus());
    }

    public static function provideStatuses(): iterable
    {
        foreach (FileImportStatus::cases() as $status) {
            yield $status->value => [$status];
        }
    }

    #[DataProvider('provideSources')]
    public function testItResolvesEverySource(ImportSource $source): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['source' => $source->value],
        ]));

        $this->assertEquals($source, $filters->getSource());
    }

    public static function provideSources(): iterable
    {
        foreach (ImportSource::cases() as $source) {
            yield $source->value => [$source];
        }
    }

    #[DataProvider('provideIgnoredFilterValues')]
    public function testItSilentlyIgnoresUnusableValues(array $query): void
    {
        $filters = FileImportOverviewFilters::fromRequest(new Request(query: $query));

        $this->assertNull($filters->getStatus());
        $this->assertNull($filters->getSource());
        $this->assertTrue($filters->isEmpty());
    }

    public static function provideIgnoredFilterValues(): iterable
    {
        yield 'values that are not backed enum values' => [
            ['filters' => ['status' => 'bogus', 'source' => 'bogus']],
        ];

        yield 'empty strings, as submitted by the "All" options' => [
            ['filters' => ['status' => '', 'source' => '']],
        ];

        yield 'nested arrays instead of scalar values' => [
            ['filters' => ['status' => ['failed'], 'source' => ['fitFile']]],
        ];

        yield 'unrelated filter names' => [
            ['filters' => ['foo' => 'bar']],
        ];
    }
}
