<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Api;

use App\Domain\Api\StoredToken;
use App\Domain\Api\Token;
use App\Infrastructure\Security\Api\ApiTokenHandler;
use App\Infrastructure\Security\Api\ApiUser;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class ApiTokenHandlerTest extends TestCase
{
    public function testItResolvesTheApiUserForTheStoredToken(): void
    {
        $token = Token::generate();
        $handler = new ApiTokenHandler(TokenRepositoryStub::holding(
            StoredToken::create($token->hash(), SerializableDateTime::some()),
        ));

        $userBadge = $handler->getUserBadgeFrom((string) $token);

        $this->assertSame(ApiUser::IDENTIFIER, $userBadge->getUserIdentifier());
    }

    public function testItSuppliesItsOwnUserLoader(): void
    {
        $token = Token::generate();
        $handler = new ApiTokenHandler(TokenRepositoryStub::holding(
            StoredToken::create($token->hash(), SerializableDateTime::some()),
        ));

        // Without a loader the authenticator falls back to the user provider.
        $userLoader = $handler->getUserBadgeFrom((string) $token)->getUserLoader();

        $this->assertNotNull($userLoader);
        $this->assertEquals(new ApiUser(), $userLoader(ApiUser::IDENTIFIER));
    }

    public function testItRejectsEveryTokenWhenNoneHasBeenGenerated(): void
    {
        $handler = new ApiTokenHandler(TokenRepositoryStub::empty());

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom((string) Token::generate());
    }

    public function testItRejectsAnEmptyTokenWhenNoneHasBeenGenerated(): void
    {
        $handler = new ApiTokenHandler(TokenRepositoryStub::empty());

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom('');
    }

    public function testItRejectsAnotherToken(): void
    {
        $handler = new ApiTokenHandler(TokenRepositoryStub::holding(
            StoredToken::create(Token::generate()->hash(), SerializableDateTime::some()),
        ));

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom((string) Token::generate());
    }

    public function testItRejectsTheTokenWithoutItsPrefix(): void
    {
        $token = Token::generate();
        $handler = new ApiTokenHandler(TokenRepositoryStub::holding(
            StoredToken::create($token->hash(), SerializableDateTime::some()),
        ));

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom(str_replace('drv_', '', (string) $token));
    }

    public function testItRejectsAnEmptyToken(): void
    {
        $handler = new ApiTokenHandler(TokenRepositoryStub::holding(
            StoredToken::create(Token::generate()->hash(), SerializableDateTime::some()),
        ));

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom('');
    }

    public function testItRejectsARevokedToken(): void
    {
        $token = Token::generate();
        $tokenRepository = TokenRepositoryStub::holding(
            StoredToken::create($token->hash(), SerializableDateTime::some()),
        );
        $handler = new ApiTokenHandler($tokenRepository);
        $tokenRepository->revoke();

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom((string) $token);
    }
}
