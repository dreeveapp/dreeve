<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Twig\AutomationTwigExtension;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class AutomationTwigExtensionTest extends ContainerTestCase
{
    private AutomationTwigExtension $extension;

    public function testDescribeConditionType(): void
    {
        $this->assertSame('Sport type', $this->extension->describeConditionType(ConditionType::SPORT_TYPE));
    }

    public function testDescribeConditionValue(): void
    {
        $this->assertSame(
            'is one of Rides',
            $this->extension->describeConditionValue(
                ConditionType::SPORT_TYPE,
                RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])
            )
        );
    }

    #[DataProvider('provideStartDateOperators')]
    public function testDescribeConditionValueForAStartDate(string $operator, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->extension->describeConditionValue(
                ConditionType::START_DATE,
                RuleConfiguration::fromConfig(['operator' => $operator, 'date' => '2024-01-01'])
            )
        );
    }

    public function testDescribeActionType(): void
    {
        $this->assertSame('Set name', $this->extension->describeActionType(ActionType::SET_NAME));
    }

    public function testDescribeActionValue(): void
    {
        $this->assertSame(
            'Morning commute',
            $this->extension->describeActionValue(
                ActionType::SET_NAME,
                RuleConfiguration::fromConfig(['name' => 'Morning commute'])
            )
        );
    }

    public function testFallsBackWhenTheComponentIsNoLongerRegistered(): void
    {
        $extension = new AutomationTwigExtension(
            new Conditions([]),
            new Actions([]),
            $this->getContainer()->get(AutomationRuleRepository::class),
            $this->getContainer()->get(TranslatorInterface::class),
        );

        $this->assertSame('sportType', $extension->describeConditionType(ConditionType::SPORT_TYPE));
        $this->assertNull($extension->describeConditionValue(ConditionType::SPORT_TYPE, RuleConfiguration::empty()));
        $this->assertSame('setName', $extension->describeActionType(ActionType::SET_NAME));
        $this->assertNull($extension->describeActionValue(ActionType::SET_NAME, RuleConfiguration::empty()));
    }

    public function testHasEnabledAutomationRules(): void
    {
        $automationRuleRepository = $this->getContainer()->get(AutomationRuleRepository::class);

        $this->assertFalse($this->extension->hasEnabledAutomationRules());

        $automationRuleRepository->add(AutomationRuleBuilder::fromDefaults()->withIsEnabled(false)->build());
        $this->assertFalse($this->extension->hasEnabledAutomationRules());

        $automationRuleRepository->add(AutomationRuleBuilder::fromDefaults()
            ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('2'))
            ->withIsEnabled(true)
            ->build());
        $this->assertTrue($this->extension->hasEnabledAutomationRules());
    }

    public static function provideStartDateOperators(): iterable
    {
        yield 'before' => ['lt', 'before 2024-01-01'];
        yield 'on or before' => ['lte', 'on or before 2024-01-01'];
        yield 'after' => ['gt', 'after 2024-01-01'];
        yield 'on or after' => ['gte', 'on or after 2024-01-01'];
        yield 'on' => ['eq', 'on 2024-01-01'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new AutomationTwigExtension(
            $this->getContainer()->get(Conditions::class),
            $this->getContainer()->get(Actions::class),
            $this->getContainer()->get(AutomationRuleRepository::class),
            $this->getContainer()->get(TranslatorInterface::class),
        );
    }
}
