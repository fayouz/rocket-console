<?php

namespace App\Security;

use Rocket\Core\Security\ApplicationUser;
use Rocket\Core\Security\ScopeGuardListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * rocket-core only lets an application acting for itself call GET /api/me. The console opens to such applications
 * (the bricks, Rocket Auth) what they need: entitlements, licence verification and public key, and the catalogue
 * (read only). Everything else (accounts, subscriptions, catalogue writes…) stays guarded.
 */
#[AsDecorator(ScopeGuardListener::class)]
final class ConsoleScopeGuardListener
{
    private const ALLOWED = [
        ['GET', '#^/api/entitlements/[a-z0-9-]+$#'],
        ['POST', '#^/api/licences/verify$#'],
        ['GET', '#^/api/licences/public-key$#'],
        ['GET', '#^/api/catalogue$#'],
    ];

    public function __construct(
        #[AutowireDecorated] private readonly ScopeGuardListener $inner,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMainRequest() && $this->security->getUser() instanceof ApplicationUser) {
            foreach (self::ALLOWED as [$method, $pattern]) {
                if ($request->isMethod($method) && preg_match($pattern, $request->getPathInfo())) {
                    return;
                }
            }
        }

        ($this->inner)($event);
    }
}
