<?php

namespace App\Tests\Infrastructure\Serialization;

use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class JsonTest extends TestCase
{
    use MatchesSnapshots;

    public function testEncodeDecode(): void
    {
        $array = ['random' => ['array' => ['with', 'children']]];

        $encoded = Json::encode($array);
        $this->assertMatchesJsonSnapshot($encoded);

        $this->assertEquals($array, Json::decode($encoded));
        $this->assertEquals($array, Json::encodeAndDecode($array));
    }

    public function testEncodePretty(): void
    {
        $array = ['random' => ['array' => ['with', 'children']]];
        $this->assertEquals(
            '{
    "random": {
        "array": [
            "with",
            "children"
        ]
    }
}',
            Json::encodePretty($array)
        );
    }

    public function testDecodeWhenInvalidJson(): void
    {
        $this->expectExceptionObject(new \JsonException('Invalid JSON detected. This is usually caused by corrupted activity data.
Please see the troubleshooting guide for steps to resolve the issue: https://docs.dreeve.app/#/troubleshooting/import-build-fails for more information.'));

        Json::decode('{"name": "Ride", "distance": 42,}');
    }
}
