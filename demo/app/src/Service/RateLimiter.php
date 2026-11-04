<?php

namespace Demo\Service;

use Demo\ApiException;
use Demo\Session\SessionInterface;

/** Sliding window limit per visitor session. The agents are live, so create calls are limited. */
final class RateLimiter
{
    public function __construct(private SessionInterface $session)
    {
    }

    public function hit(string $bucket, int $max, int $windowSeconds): void
    {
        $now = time();
        $all = $this->session->get('ratelimit', []);
        $hits = array_values(array_filter($all[$bucket] ?? [], fn($t) => $t > $now - $windowSeconds));
        if (count($hits) >= $max) {
            throw new ApiException(429, 'rate_limited', 'Too many requests, please slow down');
        }
        $hits[] = $now;
        $all[$bucket] = $hits;
        $this->session->set('ratelimit', $all);
    }
}
