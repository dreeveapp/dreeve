<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment\Overview;

use App\Domain\Segment\Overview\SegmentOverviewFilters;
use App\Domain\Segment\SegmentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SegmentOverviewFiltersTest extends TestCase
{
    public function testItReturnsNullWhenNoFiltersAreGiven(): void
    {
        $filters = SegmentOverviewFilters::fromRequest(new Request());

        $this->assertNull($filters->getName());
        $this->assertNull($filters->getType());
        $this->assertTrue($filters->isEmpty());
    }

    public function testItReadsTheFiltersFromTheNestedQueryParam(): void
    {
        $filters = SegmentOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['name' => '  Kwaremont  ', 'type' => 'custom'],
        ]));

        $this->assertSame('Kwaremont', $filters->getName());
        $this->assertSame(SegmentType::CUSTOM, $filters->getType());
        $this->assertFalse($filters->isEmpty());
    }

    #[DataProvider(methodName: 'provideIgnoredValues')]
    public function testItIgnoresBlankOrUnknownValues(array $query): void
    {
        $filters = SegmentOverviewFilters::fromRequest(new Request(query: ['filters' => $query]));

        $this->assertTrue($filters->isEmpty());
    }

    public static function provideIgnoredValues(): iterable
    {
        yield 'blank name' => [['name' => '   ']];
        yield 'unknown type' => [['type' => 'unknown']];
        yield 'empty type' => [['type' => '']];
    }
}
