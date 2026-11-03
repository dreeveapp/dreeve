<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Repository;

use App\Infrastructure\Repository\Pagination;
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase
{
    public function testNext(): void
    {
        $pagination = Pagination::fromOffsetAndLimit(0, 10);

        $this->assertEquals(
            Pagination::fromOffsetAndLimit(10, 10),
            $pagination->next(),
        );
        $this->assertEquals(
            Pagination::fromOffsetAndLimit(10, 10),
            Pagination::fromPageNumberAndSize(2, 10),
        );
    }

    public function testGetCurrentPage(): void
    {
        $this->assertEquals(1, Pagination::fromOffsetAndLimit(0, 10)->getCurrentPage());
        $this->assertEquals(2, Pagination::fromOffsetAndLimit(10, 10)->getCurrentPage());
        $this->assertEquals(4, Pagination::fromOffsetAndLimit(75, 25)->getCurrentPage());
        $this->assertEquals(1, Pagination::fromPageNumberAndSize(1, 25)->getCurrentPage());
        $this->assertEquals(3, Pagination::fromPageNumberAndSize(3, 25)->getCurrentPage());
    }

    public function testItShouldThrowWhenInvalidLimit(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid limit: 0'));
        Pagination::fromOffsetAndLimit(0, 0);
    }

    public function testItShouldThrowWhenInvalidOffset(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid offset: -1'));
        Pagination::fromOffsetAndLimit(-1, 10);
    }

    public function testItShouldThrowWhenInvalidPageSize(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('page number (0) should be > 0'));
        Pagination::fromPageNumberAndSize(0, 10);
    }
}
