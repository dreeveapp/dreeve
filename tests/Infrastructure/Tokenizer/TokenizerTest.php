<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Tokenizer;

use App\Infrastructure\Tokenizer\TokenDefinition;
use App\Infrastructure\Tokenizer\Tokenizer;
use App\Infrastructure\Tokenizer\TokenizerContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    #[DataProvider('provideReplaceExpectations')]
    public function testReplace(string $input, string $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->tokenizer->replace($input, TokenizerContext::empty()));
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

    public function testReplaceWithoutProviders(): void
    {
        $tokenizer = new Tokenizer([]);

        $this->assertSame(
            '[activity:name]',
            $tokenizer->replace('[activity:name]', TokenizerContext::empty())
        );
    }

    /**
     * @param list<string> $expectedResult
     */
    #[DataProvider('provideFindInvalidTokensExpectations')]
    public function testFindInvalidTokens(string $input, array $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->tokenizer->findInvalidTokens($input));
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideReplaceExpectations(): iterable
    {
        yield 'replaces resolvable token' => ['[activity:name] was a good one', 'Morning Ride was a good one'];
        yield 'passes modifier including colons' => ['started at [activity:start-date:H:i]', 'started at H:i'];
        yield 'leaves unknown key verbatim' => ['test [activity:pizza]', 'test [activity:pizza]'];
        yield 'leaves unresolvable token verbatim' => ['Ridden with [gear:name]', 'Ridden with [gear:name]'];
        yield 'leaves modifier on non-modifier token verbatim' => ['[activity:name:foo]', '[activity:name:foo]'];
        yield 'ignores unknown prefixes and non-token-shaped text' => ['[foo:bar] [5x400m] [note: hello] []', '[foo:bar] [5x400m] [note: hello] []'];
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideFindInvalidTokensExpectations(): iterable
    {
        yield 'invalid tokens' => ['[activity:pizza] [activity:name:foo] [gear:name:d-m-Y]', ['[activity:pizza]', '[activity:name:foo]', '[gear:name:d-m-Y]']];
        yield 'returns empty for valid text' => ['[activity:name] [activity:start-date:whatever H:i] [gear:name] [foo:bar] [5x400m] [note: hello] []', []];
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
