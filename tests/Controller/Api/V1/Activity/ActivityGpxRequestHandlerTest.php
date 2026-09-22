<?php

namespace App\Tests\Controller\Api\V1\Activity;

use App\Domain\Api\Token;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Symfony\Component\HttpFoundation\Response;

class ActivityGpxRequestHandlerTest extends ControllerWebTestCase
{
    use ProvideTestData;

    private Token $token;

    public function testItExportsTheActivityAsAGpxAttachment(): void
    {
        $this->provideFullTestSet();

        $this->client->request(
            'GET',
            '/api/v1/activities/activity-9756441741/gpx',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/gpx+xml; charset=UTF-8');
        $this->assertResponseHeaderSame(
            'Content-Disposition',
            'attachment; filename=2023-08-31-watopia-flat-forward-in-london.gpx'
        );

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<trk>', $content);
        $this->assertSame(4253, substr_count($content, '<trkpt'));
    }

    public function testItReportsWhenAnActivityHasNoGpxData(): void
    {
        $this->provideFullTestSet();

        $this->client->request(
            'GET',
            '/api/v1/activities/activity-9542782314/gpx',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame([
            'error' => 'gpx_not_available',
            'message' => 'Activity "activity-9542782314" has no GPS or time data to export as GPX.',
        ], Json::decode((string) $this->client->getResponse()->getContent()));
    }

    public function testItReportsAnUnknownActivityAsNotFound(): void
    {
        $this->client->request(
            'GET',
            '/api/v1/activities/activity-1/gpx',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'not_found',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    public function testItReportsAMalformedActivityIdAsNotFound(): void
    {
        $this->client->request(
            'GET',
            '/api/v1/activities/not-an-activity-id/gpx',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'not_found',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
