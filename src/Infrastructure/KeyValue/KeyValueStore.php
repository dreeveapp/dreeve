<?php

declare(strict_types=1);

namespace App\Infrastructure\KeyValue;

interface KeyValueStore
{
    public function save(KeyValue $keyValue): void;

    public function clear(Key $key): void;

    public function find(Key $key): Value;

    public function append(Key $key, string $value): void;
}
