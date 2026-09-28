<?php

namespace App\Signup;

use App\Entity\Member;
use Psr\Log\LoggerInterface;
use Rocket\Core\Suite\ServiceTokenProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends the sign-up verification e-mail through Rocket Mailer (POST {ROCKET_MAILER_URL}/api/emails with a suite
 * service token) when ROCKET_MAILER_URL is set; otherwise (dev, demo) the link is only logged.
 */
final class VerificationMailer
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly ServiceTokenProvider $tokens,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(ROCKET_MAILER_URL)%')] private readonly string $mailerUrl,
        #[Autowire('%env(FRONTEND_URL)%')] private readonly string $frontendUrl,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->mailerUrl);
    }

    public function verificationUrl(string $token): string
    {
        return rtrim($this->frontendUrl, '/').'/inscription/verifier?token='.$token;
    }

    /** @return bool whether the e-mail went through Rocket Mailer (false: logged only, or the mailer failed) */
    public function send(Member $owner, string $token): bool
    {
        $url = $this->verificationUrl($token);
        if (!$this->isConfigured()) {
            $this->logger->notice('Sign-up verification link (no Rocket Mailer configured): {url}', ['url' => $url, 'email' => $owner->getEmail()]);

            return false;
        }
        try {
            $status = $this->http->request('POST', rtrim($this->mailerUrl, '/').'/api/emails', [
                'auth_bearer' => $this->tokens->tokenFor('mailer'),
                'json' => [
                    'to' => $owner->getEmail(),
                    'subject' => 'Confirmez votre adresse e-mail · Rocket',
                    'text' => \sprintf("Bonjour %s,\n\nBienvenue sur Rocket ! Confirmez votre adresse e-mail pour activer votre compte « %s » :\n%s\n\nCe lien expire dans 7 jours.", $owner->getName() ?? '', $owner->getAccount()->getName(), $url),
                ],
            ])->getStatusCode();
            if ($status >= 300) {
                throw new \RuntimeException('HTTP '.$status);
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Sign-up verification e-mail failed: {error}', ['error' => $e->getMessage(), 'email' => $owner->getEmail()]);

            return false;
        }
    }
}
