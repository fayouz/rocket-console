<?php

namespace App\Signup;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Fixed-window limiter of the public endpoints, per client IP, on the application cache (no symfony/rate-limiter
 * dependency). Limits: signup 5 / hour, quote 60 / minute, verify-email 20 / hour, catalogue 120 / minute.
 */
final class RateLimiter
{
    /** name => [max hits, window in seconds] */
    public const LIMITS = [
        'signup' => [5, 3600],
        'quote' => [60, 60],
        'verify' => [20, 3600],
        'catalogue' => [120, 60],
    ];

    public function __construct(#[Autowire(service: 'cache.app')] private readonly CacheItemPoolInterface $cache)
    {
    }

    /** @throws TooManyRequestsHttpException (429) when the window is exhausted */
    public function hit(string $name, Request $request): void
    {
        [$max, $window] = self::LIMITS[$name];
        $bucket = intdiv(time(), $window);
        $item = $this->cache->getItem('public_rl.'.$name.'.'.hash('sha256', (string) $request->getClientIp()).'.'.$bucket);
        $hits = (int) ($item->isHit() ? $item->get() : 0) + 1;
        $item->set($hits)->expiresAfter($window);
        $this->cache->save($item);
        if ($hits > $max) {
            throw new TooManyRequestsHttpException(($bucket + 1) * $window - time(), 'Trop de requêtes, réessayez plus tard.');
        }
    }
}
