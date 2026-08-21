<?php

namespace App\Tests\Infrastructure\ValueObject\String;

use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class NonEmptyStringLiteralTest extends TestCase
{
    use MatchesSnapshots;

    public function testJsonSerialize(): void
    {
        $this->assertMatchesJsonSnapshot(
            Json::encode(TestNonEmptyStringLiteral::fromString('a'))
        );
    }

    public function testFromOptionalString(): void
    {
        self::assertNull(TestNonEmptyStringLiteral::fromOptionalString());
        self::assertNull(TestNonEmptyStringLiteral::fromOptionalString(''));
        self::assertNull(TestNonEmptyStringLiteral::fromOptionalString('   '));
        self::assertEquals(
            'test',
            (string) TestNonEmptyStringLiteral::fromOptionalString('test')
        );
        self::assertEquals(
            'test',
            (string) TestNonEmptyStringLiteral::fromOptionalString('  test  ')
        );
    }

    public function testToString(): void
    {
        self::assertEquals('a', (string) TestNonEmptyStringLiteral::fromString('a'));
    }

    public function testItShouldThrowWhenEmpty(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('App\\Tests\\Infrastructure\\ValueObject\\String\\TestNonEmptyStringLiteral can not be empty'));

        TestNonEmptyStringLiteral::fromString('');
    }

    #[DataProvider('provideCaseConversions')]
    public function testCaseConversions(string $input, string $expectedCamelCase, string $expectedStudlyCase, string $expectedSnakeCase, string $expectedKebabCase): void
    {
        $string = TestNonEmptyStringLiteral::fromString($input);

        self::assertEquals($expectedCamelCase, $string->camelCase());
        self::assertEquals($expectedStudlyCase, $string->studlyCase());
        self::assertEquals($expectedStudlyCase, $string->pascalCase());
        self::assertEquals($expectedSnakeCase, $string->snakeCase());
        self::assertEquals($expectedKebabCase, $string->kebabCase());
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function provideCaseConversions(): iterable
    {
        yield 'spaces' => ['hello world', 'helloWorld', 'HelloWorld', 'hello_world', 'hello-world'];
        yield 'padded with extra spaces' => [' Hello   World ', 'helloWorld', 'HelloWorld', 'hello_world', 'hello-world'];
        yield 'dashes' => ['hello-world', 'helloWorld', 'HelloWorld', 'hello_world', 'hello-world'];
        yield 'underscores' => ['hello_world', 'helloWorld', 'HelloWorld', 'hello_world', 'hello-world'];
        yield 'camelCase input' => ['helloWorld', 'helloWorld', 'HelloWorld', 'helloworld', 'helloworld'];
        yield 'leading and trailing spaces' => ['  leading and trailing  ', 'leadingAndTrailing', 'LeadingAndTrailing', 'leading_and_trailing', 'leading-and-trailing'];
        yield 'multiple spaces' => ['multiple   spaces', 'multipleSpaces', 'MultipleSpaces', 'multiple_spaces', 'multiple-spaces'];
        yield 'special characters' => ['special@#characters!', 'specialCharacters', 'SpecialCharacters', 'special_characters', 'special-characters'];
        yield 'numbers' => ['123numbers456', '123numbers456', '123numbers456', '123numbers456', '123numbers456'];
        yield 'mixed casings and separators' => ['mixed-CASE_string Example', 'mixedCASEStringExample', 'MixedCASEStringExample', 'mixed_case_string_example', 'mixed-case-string-example'];
    }
}
