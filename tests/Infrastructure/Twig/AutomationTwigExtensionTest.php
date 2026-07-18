<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Twig\AutomationTwigExtension;
use App\Tests\ContainerTestCase;
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
            $this->getContainer()->get(TranslatorInterface::class),
        );

        $this->assertSame('sportType', $extension->describeConditionType(ConditionType::SPORT_TYPE));
        $this->assertNull($extension->describeConditionValue(ConditionType::SPORT_TYPE, RuleConfiguration::empty()));
        $this->assertSame('setName', $extension->describeActionType(ActionType::SET_NAME));
        $this->assertNull($extension->describeActionValue(ActionType::SET_NAME, RuleConfiguration::empty()));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new AutomationTwigExtension(
            $this->getContainer()->get(Conditions::class),
            $this->getContainer()->get(Actions::class),
            $this->getContainer()->get(TranslatorInterface::class),
        );
    }
}
