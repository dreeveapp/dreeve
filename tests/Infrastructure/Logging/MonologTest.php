<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Logging;

use App\Infrastructure\Logging\Monolog;
use PHPUnit\Framework\TestCase;

class MonologTest extends TestCase
{
    public function testToString(): void
    {
        $monolog = new Monolog('one', 'two', 'three');

        $this->assertEquals(
            'one - two - three',
            (string) $monolog
        );
    }
}
