<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Gpx;

use App\Domain\Import\FileParser\Gpx\GpxMetadata;
use PHPUnit\Framework\TestCase;

class GpxMetadataTest extends TestCase
{
    public function testFromXml(): void
    {
        $metadata = GpxMetadata::fromXml($this->gpx('<metadata><name>Morning Ride</name><desc>Felt good</desc></metadata>'));

        $this->assertSame('Morning Ride', $metadata->getName());
        $this->assertSame('Felt good', $metadata->getDescription());
    }

    public function testFromXmlWithoutMetadata(): void
    {
        $metadata = GpxMetadata::fromXml($this->gpx(''));

        $this->assertNull($metadata->getName());
        $this->assertNull($metadata->getDescription());
    }

    public function testFromXmlWithEmptyChildren(): void
    {
        $metadata = GpxMetadata::fromXml($this->gpx('<metadata><name></name><desc>   </desc></metadata>'));

        $this->assertNull($metadata->getName());
        $this->assertNull($metadata->getDescription());
    }

    public function testFromXmlTrimsAndSupportsCdata(): void
    {
        $metadata = GpxMetadata::fromXml($this->gpx('<metadata><name>  Morning Ride  </name><desc><![CDATA[Felt <good>]]></desc></metadata>'));

        $this->assertSame('Morning Ride', $metadata->getName());
        $this->assertSame('Felt <good>', $metadata->getDescription());
    }

    private function gpx(string $metadata): \SimpleXMLElement
    {
        $xml = simplexml_load_string(sprintf('<gpx>%s<trk><trkseg/></trk></gpx>', $metadata));
        $this->assertNotFalse($xml);

        return $xml;
    }
}
