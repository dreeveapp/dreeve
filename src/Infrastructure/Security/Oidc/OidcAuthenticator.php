<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Oidc;

use App\Infrastructure\Logging\Monolog;
use App\Infrastructure\Security\Admin\AdminUserName;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

#[WithMonologChannel('errors')]
final class OidcAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly OidcConfig $oidcConfig,
        private readonly OidcProvider $oidcProvider,
        private readonly AdminUserName $adminUserName,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->oidcConfig->isEnabled()
            && 'admin_login_oidc_callback' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $loginAttempt = $request->getSession()->remove(OidcLoginAttempt::SESSION_KEY);
        $code = $request->query->getString('code');

        if (!$loginAttempt instanceof OidcLoginAttempt) {
            $this->logger->error(new Monolog('OIDC login failed', 'the session no longer holds the login attempt'));

            throw new CustomUserMessageAuthenticationException('Your sign-in session expired, please try again.');
        }

        if (!hash_equals($loginAttempt->getState(), $request->query->getString('state'))) {
            $this->logger->error(new Monolog('OIDC login failed', 'the state does not match the login attempt'));

            throw new CustomUserMessageAuthenticationException('Your sign-in session expired, please try again.');
        }

        if ('' === $code) {
            $providerError = $request->query->getString('error');
            $this->logger->error(new Monolog(
                'OIDC login failed',
                'the provider returned no code',
                sprintf('error: %s', $providerError),
                sprintf('error_description: %s', $request->query->getString('error_description')),
            ));

            if ('' === $providerError) {
                throw new CustomUserMessageAuthenticationException('Single sign-on failed, see errors.log for details.');
            }

            throw new CustomUserMessageAuthenticationException('The provider returned an error: %error%', ['%error%' => $providerError]);
        }

        try {
            $claims = $this->oidcProvider->fetchIdTokenClaims($code, $loginAttempt);
        } catch (\Exception $e) {
            $this->logger->error(new Monolog('OIDC login failed', (string) $e), ['exception' => $e]);

            throw new CustomUserMessageAuthenticationException('Single sign-on failed, see errors.log for details.', previous: $e);
        }

        if (!$this->oidcConfig->allows($claims)) {
            throw new CustomUserMessageAuthenticationException('You are not allowed to sign in to this admin panel.');
        }

        return new SelfValidatingPassport(
            new UserBadge((string) $this->adminUserName),
            [new RememberMeBadge()->enable()],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        return new RedirectResponse(
            $this->getTargetPath($request->getSession(), $firewallName) ?? $this->urlGenerator->generate('admin_file_upload')
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate('admin_login'));
    }
}
