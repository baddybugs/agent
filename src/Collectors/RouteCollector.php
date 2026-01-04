<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Support\Facades\Event;

/**
 * Route Analytics Collector
 * 
 * Tracks route-related analytics:
 * - 404 patterns
 * - Redirect chains
 * - Route model binding
 * - Slow routes
 * - Missing routes
 */
class RouteCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $redirectChain = [];

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.routes.enabled', true)) {
            return;
        }

        $this->track404Patterns();
        $this->trackRedirects();
        $this->trackModelBinding();
    }

    protected function track404Patterns(): void
    {
        if (!config('baddybugs.collectors.routes.options.track_404_patterns', true)) {
            return;
        }

        Event::listen('Illuminate\Foundation\Http\Events\RequestHandled', function ($event) {
            if ($event->response->getStatusCode() === 404) {
                $this->baddybugs->record('route', '404_not_found', [
                    'url' => $event->request->fullUrl(),
                    'path' => $event->request->path(),
                    'method' => $event->request->method(),
                    'referrer' => $event->request->header('referer'),
                    'user_agent' => $event->request->userAgent(),
                    'ip' => $event->request->ip(),
                    'user_id' => auth()->id(),
                    'pattern_guess' => $this->guessIntendedRoute($event->request->path()),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        });
    }

    protected function trackRedirects(): void
    {
        if (!config('baddybugs.collectors.routes.options.track_redirect_chains', true)) {
            return;
        }

        Event::listen('Illuminate\Foundation\Http\Events\RequestHandled', function ($event) {
            $statusCode = $event->response->getStatusCode();
            
            if (in_array($statusCode, [301, 302, 303, 307, 308])) {
                $location = $event->response->headers->get('Location');
                
                $this->baddybugs->record('route', 'redirect', [
                    'from' => $event->request->fullUrl(),
                    'to' => $location,
                    'status_code' => $statusCode,
                    'is_permanent' => in_array($statusCode, [301, 308]),
                    'route' => optional($event->request->route())->getName(),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        });
    }

    protected function trackModelBinding(): void
    {
        if (!config('baddybugs.collectors.routes.options.track_route_model_binding', true)) {
            return;
        }

        Event::listen('Illuminate\Routing\Events\RouteMatched', function ($event) {
            $route = $event->route;
            $parameters = $route->parameters();
            
            if (empty($parameters)) {
                return;
            }

            $boundModels = [];
            foreach ($parameters as $key => $value) {
                if (is_object($value) && method_exists($value, 'getKey')) {
                    $boundModels[] = [
                        'parameter' => $key,
                        'model' => get_class($value),
                        'id' => $value->getKey(),
                    ];
                }
            }

            if (!empty($boundModels)) {
                $this->baddybugs->record('route', 'model_binding', [
                    'route' => $route->getName() ?? $route->uri(),
                    'models_bound' => count($boundModels),
                    'bindings' => $boundModels,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        });
    }

    /**
     * Try to guess what route the user was trying to access
     */
    protected function guessIntendedRoute(string $path): ?string
    {
        $routes = app('router')->getRoutes();
        $bestMatch = null;
        $bestScore = 0;

        foreach ($routes as $route) {
            $routePath = $route->uri();
            $score = similar_text($path, $routePath);
            
            if ($score > $bestScore && $score > 5) {
                $bestScore = $score;
                $bestMatch = $routePath;
            }
        }

        return $bestMatch;
    }
}
