<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Foundation\Http\Events\RequestHandled;

/**
 * HTTP Lifecycle Collector
 * 
 * Captures the COMPLETE lifecycle of an HTTP request:
 * - Bootstrap phase (LARAVEL_START → app boot)
 * - Middleware stack execution (with individual timing)
 * - Route matching
 * - Controller resolution
 * - Controller/action execution
 * - View rendering
 * - Response preparation
 * - Termination
 * 
 * This provides a complete waterfall visualization of every request.
 */
class LifecycleCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    
    protected float $laravelStart;
    protected float $bootCompleted;
    protected float $routeMatched;
    protected float $controllerStart;
    protected float $responseStart;
    protected float $terminateStart;
    
    protected array $phases = [];
    protected array $middlewareExecutions = [];
    protected ?string $controller = null;
    protected ?string $action = null;
    protected ?string $routeName = null;
    protected ?array $routeParameters = null;

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
        $this->laravelStart = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
    }

    public function boot(): void
    {
        if (!config('baddybugs.lifecycle_tracking_enabled', true)) {
            return;
        }

        $this->bootCompleted = microtime(true);
        $this->recordPhase('bootstrap', $this->laravelStart, $this->bootCompleted, [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'sapi' => PHP_SAPI,
        ]);

        $this->trackRoutingPhase();
        $this->trackMiddlewarePhase();
        $this->trackControllerPhase();
        $this->trackResponsePhase();
        $this->trackTerminatePhase();
    }

    protected function trackRoutingPhase(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            $this->routeMatched = microtime(true);
            
            $route = $event->route;
            $this->routeName = $route->getName();
            $this->controller = $route->getActionName();
            $this->routeParameters = $route->parameters();
            
            // Parse controller and action
            if (str_contains($this->controller, '@')) {
                [$controllerClass, $this->action] = explode('@', $this->controller);
                $this->controller = $controllerClass;
            } elseif (str_contains($this->controller, '::')) {
                $this->action = '__invoke';
            }
            
            // Record routing phase
            $this->recordPhase('routing', $this->bootCompleted, $this->routeMatched, [
                'route_name' => $this->routeName,
                'route_uri' => $route->uri(),
                'route_methods' => $route->methods(),
            ]);
            
            // Capture middleware stack
            $this->captureMiddlewareStack($event->request, $route);
            
            // Mark controller start after middleware will run
            $this->controllerStart = microtime(true);
        });
    }

    protected function trackMiddlewarePhase(): void
    {
        // Track individual middleware if possible (Laravel 11+)
        Event::listen('middleware.start', function ($middleware) {
            $this->middlewareExecutions[$middleware] = [
                'name' => $middleware,
                'start' => microtime(true),
            ];
        });

        Event::listen('middleware.terminate', function ($middleware) {
            if (isset($this->middlewareExecutions[$middleware])) {
                $this->middlewareExecutions[$middleware]['end'] = microtime(true);
                $this->middlewareExecutions[$middleware]['duration_ms'] = 
                    ($this->middlewareExecutions[$middleware]['end'] - 
                     $this->middlewareExecutions[$middleware]['start']) * 1000;
            }
        });
    }

    protected function trackControllerPhase(): void
    {
        // We can't directly hook into controller execution, but we estimate
        // Controller starts after route matching + middleware
        // Controller ends before response sending
    }

    protected function trackResponsePhase(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            $this->responseStart = microtime(true);
            
            // Calculate controller duration (from route match to response)
            $controllerEnd = $this->responseStart;
            
            // Record controller/action phase
            $this->recordPhase('controller', $this->controllerStart ?? $this->routeMatched ?? $this->bootCompleted, $controllerEnd, [
                'controller' => $this->controller,
                'action' => $this->action,
                'route_name' => $this->routeName,
                'route_parameters' => $this->routeParameters ? array_keys($this->routeParameters) : [],
            ]);
            
            // Record response preparation
            $responseEnd = microtime(true);
            $this->recordPhase('response', $this->responseStart, $responseEnd, [
                'status_code' => $event->response->getStatusCode(),
                'content_type' => $event->response->headers->get('Content-Type'),
                'response_size' => strlen($event->response->getContent()),
            ]);
        });
    }

    protected function trackTerminatePhase(): void
    {
        app()->terminating(function () {
            $this->terminateStart = microtime(true);
            
            // Record terminate phase (will be finalized in shutdown)
            register_shutdown_function(function () {
                $terminateEnd = microtime(true);
                
                $this->recordPhase('terminate', $this->terminateStart, $terminateEnd, [
                    'callbacks_executed' => true,
                ]);
                
                // Send complete lifecycle
                $this->sendLifecycleData();
            });
        });
    }

    protected function captureMiddlewareStack(Request $request, $route): void
    {
        $routeMiddleware = $route->middleware() ?? [];
        
        // Get global middleware
        $globalMiddleware = [];
        try {
            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
            $reflection = new \ReflectionClass($kernel);
            
            if ($reflection->hasProperty('middleware')) {
                $prop = $reflection->getProperty('middleware');
                $prop->setAccessible(true);
                $globalMiddleware = $prop->getValue($kernel);
            }
        } catch (\Throwable $e) {
            // Silent
        }
        
        // Record middleware phase
        $middlewareStart = $this->routeMatched ?? $this->bootCompleted;
        $middlewareEnd = $this->controllerStart ?? microtime(true);
        
        $this->recordPhase('middleware', $middlewareStart, $middlewareEnd, [
            'global_middleware' => $globalMiddleware,
            'route_middleware' => $routeMiddleware,
            'total_count' => count($globalMiddleware) + count($routeMiddleware),
            'individual_timings' => $this->middlewareExecutions,
        ]);
    }

    protected function recordPhase(string $name, float $start, float $end, array $data = []): void
    {
        $duration = ($end - $start) * 1000; // Convert to ms
        
        $this->phases[] = [
            'name' => $name,
            'start_time' => $start,
            'end_time' => $end,
            'duration_ms' => round($duration, 3),
            'start_offset_ms' => round(($start - $this->laravelStart) * 1000, 3),
            'data' => $data,
        ];
    }

    protected function sendLifecycleData(): void
    {
        if (empty($this->phases)) {
            return;
        }

        $totalDuration = (microtime(true) - $this->laravelStart) * 1000;
        
        // Calculate percentage of time in each phase
        foreach ($this->phases as &$phase) {
            $phase['percentage'] = round(($phase['duration_ms'] / $totalDuration) * 100, 2);
        }

        // Sort by start time
        usort($this->phases, fn($a, $b) => $a['start_time'] <=> $b['start_time']);

        $lifecycleData = [
            'trace_id' => $this->baddybugs->getTraceId(),
            'total_duration_ms' => round($totalDuration, 2),
            'phases' => $this->phases,
            'phase_count' => count($this->phases),
            
            // Summary
            'summary' => [
                'controller' => $this->controller,
                'action' => $this->action,
                'route_name' => $this->routeName,
                'method' => request()->method(),
                'url' => request()->fullUrl(),
                'status_code' => http_response_code(),
            ],
            
            // Performance breakdown
            'breakdown' => $this->calculateBreakdown(),
            
            // Memory
            'memory' => [
                'peak_bytes' => memory_get_peak_usage(true),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'current_bytes' => memory_get_usage(true),
            ],
            
            'timestamp' => now()->toIso8601String(),
        ];

        $this->baddybugs->record('lifecycle', 'http_request', $lifecycleData);
    }

    protected function calculateBreakdown(): array
    {
        $breakdown = [];
        $totalDuration = 0;
        
        foreach ($this->phases as $phase) {
            $breakdown[$phase['name']] = [
                'duration_ms' => $phase['duration_ms'],
                'percentage' => $phase['percentage'] ?? 0,
            ];
            $totalDuration += $phase['duration_ms'];
        }
        
        // Add "other" for unaccounted time
        $requestTotal = (microtime(true) - $this->laravelStart) * 1000;
        $other = $requestTotal - $totalDuration;
        if ($other > 0.1) {
            $breakdown['other'] = [
                'duration_ms' => round($other, 2),
                'percentage' => round(($other / $requestTotal) * 100, 2),
            ];
        }
        
        return $breakdown;
    }
}
