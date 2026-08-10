<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;

final readonly class BadgeFragmentStub implements Fragment
{
    private function __construct(
        private FragmentType $type,
    ) {
    }

    public static function withType(FragmentType $type): self
    {
        return new self($type);
    }

    public function getPath(): string
    {
        return 'badge/dreeve';
    }

    public function getType(): FragmentType
    {
        return $this->type;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::none();
    }

    public function render(): string
    {
        return '<svg></svg>';
    }
}
