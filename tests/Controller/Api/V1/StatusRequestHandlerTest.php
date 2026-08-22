<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Application\AppVersion;
use App\Domain\Api\Token;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class StatusRequestHandlerTest extends ControllerWebTestCase
{
    private Token $token;

    public function testHandle(): void
    {
        $this->client->request('GET', '/api/v1/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $body = Json::decode((string) $this->client->getResponse()->getContent());

        $this->assertSame(
            ['app' => 'dreeve', 'version' => AppVersion::getSemanticVersion(), 'canUpload' => true],
            $body,
        );
    }

    public function testHandleInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        $this->client->request('GET', '/api/v1/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        // 200, not an error: the key is valid, the instance just cannot accept uploads.
        $this->assertResponseIsSuccessful();
        $this->assertFalse(Json::decode((string) $this->client->getResponse()->getContent())['canUpload']);
    }

    public function testItRequiresAValidKey(): void
    {
        $this->client->request('GET', '/api/v1/status');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
        $_SERVER['IMPORT_MODE'] = $_ENV['IMPORT_MODE'] = ImportMode::FILES->value;
    }
}
