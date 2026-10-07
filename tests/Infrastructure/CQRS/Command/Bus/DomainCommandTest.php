<?php

namespace App\Tests\Infrastructure\CQRS\Command\Bus;

use App\Infrastructure\Serialization\Json;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation\RunAnOperation;
use PHPUnit\Framework\TestCase;

class DomainCommandTest extends TestCase
{
    public function testItShouldJsonSerialize(): void
    {
        $this->assertEquals(
            [
                'commandName' => RunAnOperation::class,
                'payload' => ['value' => 'string', 'valueTwo' => 'defaultValue'],
            ],
            Json::decode(Json::encode(new RunAnOperation('string'))),
        );
    }
}
