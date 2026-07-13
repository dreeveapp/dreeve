<?php

namespace App\Tests\Infrastructure\ValueObject;

use App\Infrastructure\ValueObject\String\Name;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testEmpty(): void
    {
        $this->assertEquals(
            ATestCollection::empty(),
            ATestCollection::fromArray([])
        );
        $this->assertTrue(ATestCollection::empty()->isEmpty());
    }

    public function testHas(): void
    {
        $collection = ATestCollection::empty()
            ->add(Name::fromString(10));

        $this->assertTrue(
            $collection->has(Name::fromString(10))
        );
        $this->assertFalse(
            $collection->has(Name::fromString(20))
        );
    }

    public function testMergeWith(): void
    {
        $collection = ATestCollection::empty()
            ->add(Name::fromString(10))
            ->mergeWith(ATestCollection::fromArray([Name::fromString(20)]));

        $this->assertEquals(
            ATestCollection::fromArray([Name::fromString(10), Name::fromString(20)]),
            $collection
        );
    }

    public function testItShouldGuardCollectionItemType(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Item must be an instance of App\\Infrastructure\\ValueObject\\String\\Name'));

        ATestCollection::empty()->add('wrong');
    }

    public function testUnique(): void
    {
        $this->assertEquals(
            ATestCollection::fromArray([Name::fromString(10)]),
            ATestCollection::fromArray([Name::fromString(10), Name::fromString(10)])->unique()
        );
    }

    public function testReplace(): void
    {
        $collection = ATestCollection::fromArray([
            Name::fromString(10),
            Name::fromString(20),
        ]);

        $this->assertEquals(
            ATestCollection::fromArray([
                Name::fromString(30),
                Name::fromString(20),
            ]),
            $collection->replace(Name::fromString(10), Name::fromString(30))
        );
    }

    public function testReplaceItShouldThrow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Could not replace item, item not found in collection'));

        $collection = ATestCollection::fromArray([
            Name::fromString(10),
            Name::fromString(20),
        ]);

        $collection->replace(Name::fromString(30), Name::fromString(40));
    }
}
