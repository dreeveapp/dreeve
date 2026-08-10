<?php

namespace App\Tests\Controller;

use App\Controller\BadgeRequestHandler;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentRenderer;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Tests\ProvideTestData;

/**
 * What each badge renders lives next to the badge fragment. This is about the handler itself:
 * handing out an SVG that browsers are never allowed to hold on to.
 */
class BadgeRequestHandlerTest extends ControllerWebTestCase
{
    use ProvideTestData;

    public function testHandle(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/badge/dreeve.svg');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'image/svg+xml; charset=UTF-8');
        $this->assertResponseHeaderSame('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        $this->assertEquals('badge', $this->client->getRequest()->attributes->get('_route'));
    }

    public function testHandleWhenBadgeIsNotRegistered(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/badge/unknown.svg');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotServeAFragmentThatIsNotAnSvg(): void
    {
        $badgeRequestHandler = new BadgeRequestHandler(
            new FragmentRegistry([BadgeFragmentStub::withType(FragmentType::PAGE)]),
            $this->getContainer()->get(FragmentRenderer::class),
        );

        $this->assertEquals(404, $badgeRequestHandler->handle('dreeve')->getStatusCode());
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
