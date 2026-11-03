<?php

namespace App\Tests\Infrastructure\Twig;

use App\Infrastructure\Twig\MoneyTwigExtension;
use App\Tests\ContainerTestCase;
use Money\Money;

class MoneyTwigExtensionTest extends ContainerTestCase
{
    private MoneyTwigExtension $moneyTwigExtension;

    public function testDoMoneyFormat(): void
    {
        $this->assertEquals(
            '€1.00',
            $this->moneyTwigExtension->doMoneyFormat(Money::EUR(100))
        );
    }

    public function testDoMoneyDecimalFormat(): void
    {
        $this->assertEquals(
            '1500.00',
            $this->moneyTwigExtension->doMoneyDecimalFormat(Money::EUR(150000))
        );
    }

    public function testDoMoneyDecimalFormatReturnsEmptyStringForNull(): void
    {
        $this->assertEquals(
            '',
            $this->moneyTwigExtension->doMoneyDecimalFormat(null)
        );
    }

    public function testGetCurrencies(): void
    {
        $currencies = $this->moneyTwigExtension->getCurrencies();

        $this->assertContains('EUR', $currencies);
        $this->assertContains('USD', $currencies);
        $this->assertSame(array_values(array_unique($currencies)), $currencies, 'Currencies should be unique');

        $sorted = $currencies;
        sort($sorted);
        $this->assertSame($sorted, $currencies, 'Currencies should be sorted');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->moneyTwigExtension = $this->getContainer()->get(MoneyTwigExtension::class);
    }
}
