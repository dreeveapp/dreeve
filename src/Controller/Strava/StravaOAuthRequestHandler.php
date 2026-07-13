<?php

declare(strict_types=1);

namespace App\Controller\Strava;

use App\Domain\Import\ImportMode;
use App\Domain\Strava\InsufficientStravaAccessTokenScopes;
use App\Domain\Strava\InvalidStravaAccessToken;
use App\Domain\Strava\Strava;
use App\Domain\Strava\StravaClientId;
use App\Domain\Strava\StravaClientSecret;
use App\Infrastructure\Serialization\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class StravaOAuthRequestHandler
{
    public function __construct(
        private StravaClientId $stravaClientId,
        private StravaClientSecret $stravaClientSecret,
        private Strava $strava,
        private Client $client,
        private ImportMode $importMode,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/strava-oauth', name: 'strava_oauth', methods: ['GET'], priority: 2)]
    public function handle(Request $request): Response
    {
        if ($this->importMode->isFiles()) {
            return new Response($this->twig->render('html/strava-oauth/not-available-in-file-import-mode.html.twig'), Response::HTTP_OK);
        }

        try {
            $this->strava->verifyAccessToken();

            // Already authorized, load app.
            return new RedirectResponse('/', Response::HTTP_FOUND);
        } catch (\Exception $e) {
        }

        if ($e instanceof InsufficientStravaAccessTokenScopes) {
            // The user probably used the refresh token displayed on Strava's API settings page.
            // Even though the docs state explicitly that this will not work, users still do this and "report issues".
            return new Response($this->twig->render('html/strava-oauth/insufficient-scopes.html.twig', [
                'stravaClientId' => $this->stravaClientId,
                'returnUrl' => $request->getSchemeAndHttpHost().'/strava-oauth',
            ]), Response::HTTP_OK);
        }

        if ($e instanceof InvalidStravaAccessToken) {
            // The user has no valid access token yet, so we need to start the authorization process.
            if ($code = $request->query->get('code')) {
                try {
                    $response = $this->client->post('https://www.strava.com/oauth/token', [
                        RequestOptions::FORM_PARAMS => [
                            'grant_type' => 'authorization_code',
                            'client_id' => (string) $this->stravaClientId,
                            'client_secret' => (string) $this->stravaClientSecret,
                            'code' => $code,
                        ],
                    ]);

                    $refreshToken = Json::decode($response->getBody()->getContents())['refresh_token'];

                    return new Response($this->twig->render('html/strava-oauth/refresh-token.html.twig', [
                        'refreshToken' => $refreshToken,
                        'url' => $request->getSchemeAndHttpHost(),
                    ]), Response::HTTP_OK);
                } catch (ClientException|RequestException $e) {
                    $error = $e->getMessage();
                    if (($response = $e->getResponse()) instanceof ResponseInterface) {
                        $error = $response->getBody()->getContents();
                    }
                }
            }

            return new Response($this->twig->render('html/strava-oauth/start-authorization.html.twig', [
                'stravaClientId' => $this->stravaClientId,
                'returnUrl' => $request->getSchemeAndHttpHost().'/strava-oauth',
                'error' => $error ?? null,
            ]), Response::HTTP_OK);
        }

        // Any other exception is unexpected.
        return new Response($this->twig->render('html/strava-oauth/error-page.html.twig', [
            'error' => $e->getMessage(),
        ]), Response::HTTP_OK);
    }
}
