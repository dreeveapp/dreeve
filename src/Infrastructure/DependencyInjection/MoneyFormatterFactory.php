<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection;

use App\Domain\Settings\SettingsRepository;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\MoneyFormatter;

final readonly class MoneyFormatterFactory
{
    public function __construct(
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function __invoke(): MoneyFormatter
    {
        $numberFormatter = new \NumberFormatter(
            locale: $this->settingsRepository->appearance()->getLocale()->value,
            style: \NumberFormatter::CURRENCY
        );

        return new IntlMoneyFormatter(
            formatter: $numberFormatter,
            currencies: new ISOCurrencies()
        );
    }
}
