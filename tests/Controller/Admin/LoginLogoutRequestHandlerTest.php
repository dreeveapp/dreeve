<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\LoginLogoutRequestHandler;
use App\Infrastructure\Security\Oidc\OidcConfig;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class LoginLogoutRequestHandlerTest extends AdminWebTestCase
{
    public function testLoginPageIsAccessibleForAnonymousUsers(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('input[name="_username"]'));
        $this->assertCount(1, $crawler->filter('input[name="_password"]'));
        $this->assertCount(1, $crawler->filter('input[name="_csrf_token"]'));
        $this->assertCount(0, $crawler->filter('a[href$="/admin/login/oidc"]'));
    }

    public function testAuthenticatedUsersAreRedirectedAwayFromTheLoginPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/login');

        $this->assertResponseRedirects('/admin/upload');
    }

    public function testCanLogInWithValidCredentials(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');

        $this->client->submit($crawler->filter('form')->form([
            '_username' => self::ADMIN_USERNAME,
            '_password' => self::ADMIN_PASSWORD,
        ]));

        $this->assertResponseRedirects('/admin/upload');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testCannotLogInWithInvalidCredentials(): void
    {
        $crawler = $this->client->request('GET', '/admin/login');

        $this->client->submit($crawler->filter('form')->form([
            '_username' => self::ADMIN_USERNAME,
            '_password' => 'wrong-password',
        ]));

        $this->assertResponseRedirects('/admin/login');

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('.text-red-700'));
    }

    public function testLoggingOutRedirectsToTheLoginPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/upload');
        $this->assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[action$="/admin/logout"]')->form());
        $this->assertResponseRedirects('/admin/login');

        $this->client->request('GET', '/admin/upload');
        $this->assertResponseRedirects('/admin/login', Response::HTTP_FOUND);
    }

    public function testLogoutIsHandledByTheFirewall(): void
    {
        $handler = new LoginLogoutRequestHandler(
            $this->createStub(Environment::class),
            $this->createStub(Security::class),
            $this->createStub(UrlGeneratorInterface::class),
            OidcConfig::fromStrings('', '', '', ''),
        );

        $this->expectExceptionObject(new \LogicException('Intercepted by the logout key on the firewall.'));

        $handler->logout();
    }
}
