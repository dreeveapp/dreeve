<?php

namespace App\Tests\Domain\Milestone;

use App\Domain\Milestone\MilestoneIdFactory;
use PHPUnit\Framework\TestCase;

class MilestoneIdFactoryTest extends TestCase
{
    public function testNext(): void
    {
        $factory = new MilestoneIdFactory();

        $this->assertEquals('milestone-1', (string) $factory->next());
        $this->assertEquals('milestone-2', (string) $factory->next());
        $this->assertEquals('milestone-3', (string) $factory->next());
    }

    public function testNextStartsOverForANewFactory(): void
    {
        $factory = new MilestoneIdFactory();
        $factory->next();

        $this->assertEquals('milestone-1', (string) (new MilestoneIdFactory())->next());
    }
}
