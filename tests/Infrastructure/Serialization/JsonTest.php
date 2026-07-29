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

    public function testDecodeLazy(): void
    {
        $this->assertEquals(
            ['first', 'second', 'third'],
            $this->namesIn(Json::decodeLazy(
                json: file_get_contents($this->fixture()) ?: '',
                pointer: '/items'
            ))
        );
    }

    public function testDecodeLazyFromFile(): void
    {
        $this->assertEquals(
            ['first', 'second', 'third'],
            $this->namesIn(Json::decodeLazyFromFile(
                filePath: $this->fixture(),
                pointer: '/items'
            ))
        );
    }

    public function testDecodeWhenInvalidJson(): void
    {
        $this->expectExceptionObject(new \JsonException('Invalid JSON detected. This is usually caused by corrupted activity data.
Please see the troubleshooting guide for steps to resolve the issue: https://docs.dreeve.app/#/troubleshooting/import-build-fails for more information.'));

        Json::decode('{"name": "Ride", "distance": 42,}');
    }

    private function fixture(): string
    {
        return __DIR__.'/fixtures/lazy-decode.json';
    }

    /**
     * @param iterable<array{id: int, attributes: array{name: string}}> $items
     *
     * @return list<string>
     */
    private function namesIn(iterable $items): array
    {
        $names = [];
        foreach ($items as $item) {
            $names[] = $item['attributes']['name'];
        }

        return $names;
    }
}
