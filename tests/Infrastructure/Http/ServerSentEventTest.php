<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\ServerSentEvent;
use PHPUnit\Framework\TestCase;

class ServerSentEventTest extends TestCase
{
    public function testToString(): void
    {
        $output = null;
        foreach (new ServerSentEvent(
            data: 'WAW ERROR',
            type: 'error'
        ) as $yield) {
            $output .= $yield;
        }

        $this->assertEquals(
            'event: error
data: WAW ERROR

',
            $output
        );
    }
}
