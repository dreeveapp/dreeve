<?php

declare(strict_types=1);

namespace App\Infrastructure\Mutex;

enum LockName: string
{
    case IMPORT_DATA = 'importData';

    public function key(): string
    {
        return 'lock.'.$this->value;
    }
}
