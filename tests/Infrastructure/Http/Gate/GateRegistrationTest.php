<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Http\Gate\AdminAllowedIpGate;
use App\Infrastructure\Http\Gate\Gate;
use App\Infrastructure\Http\Gate\GateRequestListener;
use App\Infrastructure\Http\Gate\ValidAppSettingsGate;
use App\Infrastructure\Http\Gate\ValidStravaRefreshTokenGate;
use App\Tests\ContainerTestCase;

class GateRegistrationTest extends ContainerTestCase
{
    public function testGatesAreRegisteredAndOrderedByPriority(): void
    {
        $gateClasses = [];
        foreach ($this->registeredGates() as $gate) {
            $gateClasses[] = $gate::class;
        }

        $this->assertSame([
            AdminAllowedIpGate::class,          // priority 100
            ValidStravaRefreshTokenGate::class, // priority 90
            ValidAppSettingsGate::class,        // priority 80
        ], $gateClasses);
    }

    /**
     * @return iterable<Gate>
     */
    private function registeredGates(): iterable
    {
        $listener = $this->getContainer()->get(GateRequestListener::class);

        /** @var iterable<Gate> $gates */
        $gates = new \ReflectionProperty(GateRequestListener::class, 'gates')->getValue($listener);

        return $gates;
    }
}
