<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Infrastructure\Repository\Pagination;

interface SegmentRepository
{
    public function add(Segment $segment): void;

    public function update(Segment $segment): void;

    public function delete(Segment $segment): void;

    public function find(SegmentId $segmentId): Segment;

    public function findAll(Pagination $pagination): Segments;

    public function findByType(SegmentType $type): Segments;

    /**
     * @return SegmentId[]
     */
    public function findSegmentsIdsMissingDetails(): array;

    public function count(): int;

    public function deleteOrphanedImported(): void;
}
