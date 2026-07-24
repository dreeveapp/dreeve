<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Tokenizer;

use App\Infrastructure\Tokenizer\TokenDefinition;
use App\Infrastructure\Tokenizer\Tokenizer;
use App\Infrastructure\Tokenizer\TokenizerContext;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    public function testReplace(): void
    {
        $this->assertSame(
            'Morning Ride was a good one',
            $this->tokenizer->replace('[activity:name] was a good one', TokenizerContext::empty())
        );
    }

    public function testReplaceMultipleTokensInOneString(): void
    {
        $this->assertSame(
            'Morning Ride on default-format with Canyon',
            $this->tokenizer->replace(
                '[activity:name] on [activity:start-date] with [gear:name]',
                TokenizerContext::empty()->with(GearStub::withName('Canyon'))
            )
        );
    }

    public function testReplacePassesModifierIncludingColons(): void
    {
        $this->assertSame(
            'started at H:i',
            $this->tokenizer->replace('started at [activity:start-date:H:i]', TokenizerContext::empty())
        );
    }

    public function testReplaceLeavesUnknownKeyVerbatim(): void
    {
        $this->assertSame(
            'test [activity:pizza]',
            $this->tokenizer->replace('test [activity:pizza]', TokenizerContext::empty())
        );
    }

    public function testReplaceLeavesUnresolvableTokenVerbatim(): void
    {
        $this->assertSame(
            'Ridden with [gear:name]',
            $this->tokenizer->replace('Ridden with [gear:name]', TokenizerContext::empty())
        );
    }

    public function testReplaceLeavesModifierOnNonModifierTokenVerbatim(): void
    {
        $this->assertSame(
            '[activity:name:foo]',
            $this->tokenizer->replace('[activity:name:foo]', TokenizerContext::empty())
        );
    }

    public function testReplaceIgnoresUnknownPrefixesAndNonTokenShapedText(): void
    {
        $text = '[foo:bar] [5x400m] [note: hello] []';

        $this->assertSame($text, $this->tokenizer->replace($text, TokenizerContext::empty()));
    }

    public function testReplaceWithoutProviders(): void
    {
        $tokenizer = new Tokenizer([]);

        $this->assertSame(
            '[activity:name]',
            $tokenizer->replace('[activity:name]', TokenizerContext::empty())
        );
    }

    public function testFindInvalidTokens(): void
    {
        $this->assertSame(
            ['[activity:pizza]', '[activity:name:foo]', '[gear:name:d-m-Y]'],
            $this->tokenizer->findInvalidTokens('[activity:pizza] [activity:name:foo] [gear:name:d-m-Y]')
        );
    }

    public function testFindInvalidTokensReturnsEmptyForValidText(): void
    {
        $this->assertSame(
            [],
            $this->tokenizer->findInvalidTokens('[activity:name] [activity:start-date:whatever H:i] [gear:name] [foo:bar] [5x400m] [note: hello] []')
        );
    }

    public function testFindInvalidTokensWithoutProviders(): void
    {
        $tokenizer = new Tokenizer([]);

        $this->assertSame([], $tokenizer->findInvalidTokens('[activity:name]'));
    }

    public function testGetTokenDefinitions(): void
    {
        $this->assertSame(
            ['[activity:name]', '[activity:start-date:d-m-Y]', '[gear:name]'],
            array_map(
                static fn (TokenDefinition $definition): string => $definition->getExampleTokenString(),
                $this->tokenizer->getTokenDefinitions()
            )
        );
    }

    public function testItThrowsOnDuplicatePrefix(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Duplicate token provider for prefix "activity".'));

        new Tokenizer([new ActivityTokenProviderStub(), new ActivityTokenProviderStub()]);
    }

    public function testTokenizerContextIsImmutable(): void
    {
        $context = TokenizerContext::empty();
        $context->with(GearStub::withName('Canyon'));

        $this->assertNull($context->get(GearStub::class));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenizer = new Tokenizer([
            new ActivityTokenProviderStub(),
            new GearTokenProviderStub(),
        ]);
    }
}
