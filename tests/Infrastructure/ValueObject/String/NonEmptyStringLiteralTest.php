<?php

namespace App\Tests\Infrastructure\ValueObject\String;

use App\Infrastructure\Serialization\Json;
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
        self::assertEquals(
            'test',
            (string) TestNonEmptyStringLiteral::fromOptionalString('test')
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

    public function testCamelCase(): void
    {
        self::assertEquals('helloWorld', TestNonEmptyStringLiteral::fromString('hello world')->camelCase());
        self::assertEquals('helloWorld', TestNonEmptyStringLiteral::fromString(' Hello   World ')->camelCase());
        self::assertEquals('helloWorld', TestNonEmptyStringLiteral::fromString('hello-world')->camelCase());
        self::assertEquals('helloWorld', TestNonEmptyStringLiteral::fromString('hello_world')->camelCase());
        self::assertEquals('helloWorld', TestNonEmptyStringLiteral::fromString('helloWorld')->camelCase());
        self::assertEquals('leadingAndTrailing', TestNonEmptyStringLiteral::fromString('  leading and trailing  ')->camelCase());
        self::assertEquals('multipleSpaces', TestNonEmptyStringLiteral::fromString('multiple   spaces')->camelCase());
        self::assertEquals('specialCharacters', TestNonEmptyStringLiteral::fromString('special@#characters!')->camelCase());
        self::assertEquals('123numbers456', TestNonEmptyStringLiteral::fromString('123numbers456')->camelCase());
        self::assertEquals('mixedCASEStringExample', TestNonEmptyStringLiteral::fromString('mixed-CASE_string Example')->camelCase());
    }

    public function testStudlyCase(): void
    {
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString('hello world')->studlyCase());
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString(' Hello   World ')->studlyCase());
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString('hello-world')->studlyCase());
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString('hello_world')->studlyCase());
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString('helloWorld')->studlyCase());
        self::assertEquals('LeadingAndTrailing', TestNonEmptyStringLiteral::fromString('  leading and trailing  ')->studlyCase());
        self::assertEquals('MultipleSpaces', TestNonEmptyStringLiteral::fromString('multiple   spaces')->studlyCase());
        self::assertEquals('SpecialCharacters', TestNonEmptyStringLiteral::fromString('special@#characters!')->studlyCase());
        self::assertEquals('123numbers456', TestNonEmptyStringLiteral::fromString('123numbers456')->studlyCase());
        self::assertEquals('MixedCASEStringExample', TestNonEmptyStringLiteral::fromString('mixed-CASE_string Example')->studlyCase());
    }

    public function testPascalCase(): void
    {
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString('hello world')->pascalCase());
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString(' Hello   World ')->pascalCase());
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString('hello-world')->pascalCase());
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString('hello_world')->pascalCase());
        self::assertEquals('HelloWorld', TestNonEmptyStringLiteral::fromString('helloWorld')->pascalCase());
        self::assertEquals('LeadingAndTrailing', TestNonEmptyStringLiteral::fromString('  leading and trailing  ')->pascalCase());
        self::assertEquals('MultipleSpaces', TestNonEmptyStringLiteral::fromString('multiple   spaces')->pascalCase());
        self::assertEquals('SpecialCharacters', TestNonEmptyStringLiteral::fromString('special@#characters!')->pascalCase());
        self::assertEquals('123numbers456', TestNonEmptyStringLiteral::fromString('123numbers456')->pascalCase());
        self::assertEquals('MixedCASEStringExample', TestNonEmptyStringLiteral::fromString('mixed-CASE_string Example')->pascalCase());
    }

    public function testSnakeCase(): void
    {
        self::assertEquals('hello_world', TestNonEmptyStringLiteral::fromString('hello world')->snakeCase());
        self::assertEquals('hello_world', TestNonEmptyStringLiteral::fromString(' Hello   World ')->snakeCase());
        self::assertEquals('hello_world', TestNonEmptyStringLiteral::fromString('hello-world')->snakeCase());
        self::assertEquals('hello_world', TestNonEmptyStringLiteral::fromString('hello_world')->snakeCase());
        self::assertEquals('leading_and_trailing', TestNonEmptyStringLiteral::fromString('  leading and trailing  ')->snakeCase());
        self::assertEquals('multiple_spaces', TestNonEmptyStringLiteral::fromString('multiple   spaces')->snakeCase());
        self::assertEquals('special_characters', TestNonEmptyStringLiteral::fromString('special@#characters!')->snakeCase());
        self::assertEquals('123numbers456', TestNonEmptyStringLiteral::fromString('123numbers456')->snakeCase());
        self::assertEquals('mixed_case_string_example', TestNonEmptyStringLiteral::fromString('mixed-CASE_string Example')->snakeCase());
    }

    public function testKebabCase(): void
    {
        self::assertEquals('hello-world', TestNonEmptyStringLiteral::fromString('hello world')->kebabCase());
        self::assertEquals('hello-world', TestNonEmptyStringLiteral::fromString(' Hello   World ')->kebabCase());
        self::assertEquals('hello-world', TestNonEmptyStringLiteral::fromString('hello-world')->kebabCase());
        self::assertEquals('hello-world', TestNonEmptyStringLiteral::fromString('hello_world')->kebabCase());
        self::assertEquals('leading-and-trailing', TestNonEmptyStringLiteral::fromString('  leading and trailing  ')->kebabCase());
        self::assertEquals('multiple-spaces', TestNonEmptyStringLiteral::fromString('multiple   spaces')->kebabCase());
        self::assertEquals('special-characters', TestNonEmptyStringLiteral::fromString('special@#characters!')->kebabCase());
        self::assertEquals('123numbers456', TestNonEmptyStringLiteral::fromString('123numbers456')->kebabCase());
        self::assertEquals('mixed-case-string-example', TestNonEmptyStringLiteral::fromString('mixed-CASE_string Example')->kebabCase());
    }
}
