<?php

namespace App\Tests\Domain\Activity\Eddington\Config;

use App\Domain\Activity\Eddington\Config\EddingtonConfigItem;
use App\Domain\Activity\SportType\SportTypes;
use PHPUnit\Framework\TestCase;

class EddingtonConfigItemTest extends TestCase
{
    public function testGetId(): void
    {
        $configItem = EddingtonConfigItem::create(
            label: 'Test With 999% weird ° $ chars YO',
            sportTypesToInclude: SportTypes::empty(),
            showInDashboardWidget: false
        );

        $this->assertEquals(
            'test-with-999-weird-chars-yo',
            $configItem->getId(),
        );
    }
}
