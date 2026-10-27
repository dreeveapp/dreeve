<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ECharts;

use App\Infrastructure\ECharts\EChartsCallback;
use PHPUnit\Framework\TestCase;

class EChartsCallbackTest extends TestCase
{
    public function testEveryCallbackIsRegisteredInJavascript(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 3).'/public/js/features/charts/echarts-callbacks.js');
        $registry = substr($javascript, (int) strpos($javascript, 'window.dreeve.callbacks = {'));
        preg_match_all('/^ {8}(\w+)\s*[,:]/m', $registry, $matches);

        $callbackNames = array_map(
            fn (\ReflectionMethod $method): string => $method->getName(),
            array_filter(
                new \ReflectionClass(EChartsCallback::class)->getMethods(\ReflectionMethod::IS_STATIC),
                fn (\ReflectionMethod $method): bool => $method->isPublic(),
            ),
        );

        $this->assertNotEmpty($callbackNames);
        $this->assertSame([], array_values(array_diff($callbackNames, $matches[1])));
    }
}
