<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate Limit Collector
 * 
 * Tracks rate limiting activity:
 * - Hits against rate limiters
 * - Rejections (429 responses)
 * - Rate limit by user/IP/route
 * - Throttle patterns
 */
class RateLimitCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $hits = [];

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.rate_limit.enabled', true)) {
            return;
        }

        // Skip in console - no request/response available
        if (app()->runningInConsole()) {
            return;
        }

        $this->trackThrottleMiddleware();
        $this->trackOnTerminate();
    }

    protected function trackThrottleMiddleware(): void
    {
        // Track when rate limit is exceeded
        Event::listen('Illuminate\Http\Events\RequestHandled', function ($event) {
            $response = $event->response;
            
            if ($response->getStatusCode() === 429) {
                $this->baddybugs->record('rate_limit', 'exceeded', [
                    'url' => $event->request->fullUrl(),
                    'method' => $event->request->method(),
                    'ip' => $event->request->ip(),
                    'user_id' => auth()->id(),
                    'user_agent' => $event->request->userAgent(),
                    'route' => optional($event->request->route())->getName(),
                    'retry_after' => $response->headers->get('Retry-After'),
                    'x_ratelimit_limit' => $response->headers->get('X-RateLimit-Limit'),
                    'x_ratelimit_remaining' => $response->headers->get('X-RateLimit-Remaining'),
                    'timestamp' => now()->toIso8601String(),
                    'severity' => 'warning',
                ]);
            }
        });
    }

    protected function trackOnTerminate(): void
    {
        app()->terminating(function () {
            $this->collectRateLimitMetrics();
        });
    }

    protected function collectRateLimitMetrics(): void
    {
        // Safe request access
        try {
            if (app()->runningInConsole() && !app()->bound('request')) {
                return;
            }
            $request = app('request');
        } catch (\Throwable $e) {
            return;
        }
        
        try {
            $response = app()->bound('response') ? app('response') : null;
        } catch (\Throwable $e) {
            $response = null;
        }
        
        // Check for rate limit headers in response
        if ($response && method_exists($response, 'headers')) {
            $limit = $response->headers->get('X-RateLimit-Limit');
            $remaining = $response->headers->get('X-RateLimit-Remaining');
            
            if ($limit !== null && $remaining !== null) {
                $usage = ((int)$limit - (int)$remaining) / max(1, (int)$limit);
                
                // Only record if usage is significant (> 50%)
                if ($usage > 0.5) {
                    $this->baddybugs->record('rate_limit', 'usage', [
                        'route' => optional($request->route())->getName(),
                        'url' => $request->path(),
                        'limit' => (int)$limit,
                        'remaining' => (int)$remaining,
                        'usage_percentage' => round($usage * 100, 2),
                        'user_id' => auth()->id(),
                        'ip' => $request->ip(),
                        'timestamp' => now()->toIso8601String(),
                    ]);
                }
            }
        }
    }

    /**
     * Manually track a rate limit hit
     */
    public function trackHit(string $key, int $maxAttempts, int $decaySeconds): void
    {
        $attempts = RateLimiter::attempts($key);
        $remaining = max(0, $maxAttempts - $attempts);
        
        $this->baddybugs->record('rate_limit', 'hit', [
            'key' => $key,
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'remaining' => $remaining,
            'decay_seconds' => $decaySeconds,
            'usage_percentage' => round(($attempts / $maxAttempts) * 100, 2),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
