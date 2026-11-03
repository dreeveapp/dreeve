<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Request;

use App\Infrastructure\Http\Request\PaginationFromRequest;
use App\Infrastructure\Repository\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PaginationFromRequestTest extends TestCase
{
    public function testItFallsBackToDefaultsWhenNoPaginationIsGiven(): void
    {
        $this->assertEquals(
            Pagination::fromPageNumberAndSize(1, 25),
            $this->resolvePagination(new Request())
        );
    }

    public function testItReadsPageAndSizeFromTheNestedQueryParam(): void
    {
        $this->assertEquals(
            Pagination::fromPageNumberAndSize(3, 10),
            $this->resolvePagination(new Request(query: [
                'pagination' => ['page' => '3', 'size' => '10'],
            ]))
        );
    }

    public function testItFallsBackToDefaultsPerMissingKey(): void
    {
        $this->assertEquals(
            Pagination::fromPageNumberAndSize(4, 25),
            $this->resolvePagination(new Request(query: [
                'pagination' => ['page' => '4'],
            ]))
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('provideInvalidPagination')]
    public function testItThrows(array $query, \InvalidArgumentException $expectedException): void
    {
        $this->expectExceptionObject($expectedException);

        $this->resolvePagination(new Request(query: $query));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, \InvalidArgumentException}>
     */
    public static function provideInvalidPagination(): iterable
    {
        yield 'page is not an integer' => [
            ['pagination' => ['page' => 'not-a-number']],
            new \InvalidArgumentException('page number should be a valid integer'),
        ];

        yield 'page is an array' => [
            ['pagination' => ['page' => ['nested']]],
            new \InvalidArgumentException('page number should be a valid integer'),
        ];

        yield 'size is not an integer' => [
            ['pagination' => ['size' => '12.5']],
            new \InvalidArgumentException('page size should be a valid integer'),
        ];

        yield 'size exceeds the maximum' => [
            ['pagination' => ['size' => '250']],
            new \InvalidArgumentException('page size (250) should not exceed 100'),
        ];

        yield 'page is below one' => [
            ['pagination' => ['page' => '0']],
            new \InvalidArgumentException('page number (0) should be > 0'),
        ];
    }

    private function resolvePagination(Request $request): Pagination
    {
        $host = new class {
            use PaginationFromRequest;

            public function resolve(Request $request): Pagination
            {
                return $this->paginationFromRequest($request);
            }
        };

        return $host->resolve($request);
    }
}
