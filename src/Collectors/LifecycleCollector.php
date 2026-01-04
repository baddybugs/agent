<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;

/**
 * HTTP Lifecycle Collector (Unified)
 * 
 * Captures the COMPLETE lifecycle of an HTTP request with Nightwatch-level granularity:
 * 
 * PHASES:
 * - Bootstrap phase (LARAVEL_START → app boot)
 * - Middleware stack execution
 * - Controller/action execution
 * - Response preparation
 * 
 * SPANS (individual events):
 * - Every SQL query with exact timing
 * - Every cache operation (hit/miss/write)
 * - Every outgoing HTTP request
 * - Every job dispatched
 * 
 * This provides a complete waterfall visualization of every request.
 */
class LifecycleCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    
    // Timing markers
    protected float $requestStart;
    protected ?float $bootstrapEnd = null;
    protected ?float $middlewareStart = null;
    protected ?float $controllerStart = null;
    protected ?float $controllerEnd = null;
    protected ?float $responseStart = null;
    
    // Request metadata
    protected ?string $controller = null;
    protected ?string $action = null;
    protected ?string $routeName = null;
    protected ?array $routeParameters = null;
    protected array $routeMethods = [];
    protected ?string $routeUri = null;
    
    // Middleware tracking
    protected array $globalMiddleware = [];
    protected array $routeMiddleware = [];
    
    // Spans (individual events)
    protected array $spans = [];
    protected int $spanCounter = 0;
    
    // Pending operations (for timing)
    protected array $pendingHttpRequests = [];

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
        $this->requestStart = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
    }

    public function boot(): void
    {
        if (!config('baddybugs.lifecycle_tracking_enabled', true)) {
            return;
        }
        
        // Skip in console - no request/response bindings available
        if (app()->runningInConsole()) {
            return;
        }

        $this->bootstrapEnd = microtime(true);
        
        // Track all phases and events
        $this->trackRoutingPhase();
        $this->trackQueries();
        $this->trackCacheOperations();
        $this->trackOutgoingRequests();
        $this->trackJobEvents();
        $this->trackResponsePhase();
        
        // Send on request completion
        $this->sendOnCompletion();
    }

    protected function trackRoutingPhase(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            $this->middlewareStart = microtime(true);
            
            $route = $event->route;
            $this->routeName = $route->getName();
            $this->routeUri = $route->uri();
            $this->routeMethods = $route->methods();
            $this->routeParameters = $route->parameters();
            
            // Parse controller and action
            $actionName = $route->getActionName();
            if (str_contains($actionName, '@')) {
                [$this->controller, $this->action] = explode('@', $actionName);
            } elseif (str_contains($actionName, '::')) {
                $this->controller = $actionName;
                $this->action = '__invoke';
            } else {
                $this->controller = $actionName;
                $this->action = '__invoke';
            }
            
            // Capture middleware stack
            $this->captureMiddlewareStack($route);
            
            // Mark controller start (after middleware will run)
            $this->controllerStart = microtime(true);
        });
    }

    protected function captureMiddlewareStack($route): void
    {
        $this->routeMiddleware = $route->middleware() ?? [];
        
        try {
            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
            $reflection = new \ReflectionClass($kernel);
            
            if ($reflection->hasProperty('middleware')) {
                $prop = $reflection->getProperty('middleware');
                $prop->setAccessible(true);
                $this->globalMiddleware = $prop->getValue($kernel);
            }
        } catch (\Throwable $e) {
            // Silent
        }
    }

    protected function trackQueries(): void
    {
        DB::listen(function ($query) {
            $endTime = microtime(true);
            $startTime = $endTime - ($query->time / 1000); // query->time is in ms
            
            $sql = $query->sql;
            
            // Truncate SQL for label
            $label = strlen($sql) > 80 
                ? substr($sql, 0, 80) . '...' 
                : $sql;
            
            $this->addSpan([
                'type' => 'QUERY',
                'label' => $label,
                'sql' => $sql,
                'bindings' => $query->bindings ?? [],
                'connection' => $query->connectionName,
                'duration_ms' => round($query->time, 2),
                'start_offset_ms' => round(($startTime - $this->requestStart) * 1000, 2),
            ]);
        });
    }

    protected function trackCacheOperations(): void
    {
        // Cache hit
        Event::listen(\Illuminate\Cache\Events\CacheHit::class, function ($event) {
            $now = microtime(true);
            $this->addSpan([
                'type' => 'CACHE_HIT',
                'label' => $event->key,
                'key' => $event->key,
                'duration_ms' => 0.5, // Estimated
                'start_offset_ms' => round(($now - $this->requestStart) * 1000, 2),
            ]);
        });
        
        // Cache miss
        Event::listen(\Illuminate\Cache\Events\CacheMissed::class, function ($event) {
            $now = microtime(true);
            $this->addSpan([
                'type' => 'CACHE_MISS',
                'label' => $event->key,
                'key' => $event->key,
                'duration_ms' => 0.5, // Estimated
                'start_offset_ms' => round(($now - $this->requestStart) * 1000, 2),
            ]);
        });
        
        // Cache write
        Event::listen(\Illuminate\Cache\Events\KeyWritten::class, function ($event) {
            $now = microtime(true);
            $this->addSpan([
                'type' => 'CACHE_WRITE',
                'label' => $event->key,
                'key' => $event->key,
                'duration_ms' => 1.0, // Estimated
                'start_offset_ms' => round(($now - $this->requestStart) * 1000, 2),
            ]);
        });
    }

    protected function trackOutgoingRequests(): void
    {
        // Request starting
        Event::listen(RequestSending::class, function ($event) {
            $request = $event->request;
            $key = spl_object_hash($request);
            
            $this->pendingHttpRequests[$key] = [
                'start_time' => microtime(true),
                'method' => $request->method(),
                'url' => $request->url(),
            ];
        });
        
        // Response received
        Event::listen(ResponseReceived::class, function ($event) {
            $request = $event->request;
            $response = $event->response;
            $key = spl_object_hash($request);
            
            if (!isset($this->pendingHttpRequests[$key])) {
                return;
            }
            
            $startInfo = $this->pendingHttpRequests[$key];
            $endTime = microtime(true);
            $duration = ($endTime - $startInfo['start_time']) * 1000;
            
            // Build label: METHOD host/path
            $parsedUrl = parse_url($startInfo['url']);
            $host = $parsedUrl['host'] ?? '';
            $path = $parsedUrl['path'] ?? '/';
            $label = $startInfo['method'] . ' ' . $host . $path;
            
            $this->addSpan([
                'type' => 'OUTGOING_REQUEST',
                'label' => $label,
                'method' => $startInfo['method'],
                'url' => $startInfo['url'],
                'status_code' => $response->status(),
                'duration_ms' => round($duration, 2),
                'start_offset_ms' => round(($startInfo['start_time'] - $this->requestStart) * 1000, 2),
            ]);
            
            unset($this->pendingHttpRequests[$key]);
        });
    }

    protected function trackJobEvents(): void
    {
        Event::listen('Illuminate\Queue\Events\JobQueued', function ($event) {
            $now = microtime(true);
            
            $jobClass = is_object($event->job) ? get_class($event->job) : (string) $event->job;
            
            $this->addSpan([
                'type' => 'JOB_DISPATCHED',
                'label' => class_basename($jobClass),
                'job_class' => $jobClass,
                'connection' => $event->connectionName ?? 'default',
                'duration_ms' => 0,
                'start_offset_ms' => round(($now - $this->requestStart) * 1000, 2),
            ]);
        });
    }

    protected function trackResponsePhase(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            $this->controllerEnd = microtime(true);
            $this->responseStart = microtime(true);
        });
    }

    protected function addSpan(array $span): void
    {
        $span['span_id'] = ++$this->spanCounter;
        $span['trace_id'] = $this->baddybugs->getTraceId();
        $this->spans[] = $span;
    }

    protected function sendOnCompletion(): void
    {
        app()->terminating(function () {
            $requestEnd = microtime(true);
            $totalDuration = ($requestEnd - $this->requestStart) * 1000;
            
            // Build ordered spans with depth
            $orderedSpans = $this->buildOrderedSpans();
            
            // Build lifecycle data
            $lifecycleData = [
                'trace_id' => $this->baddybugs->getTraceId(),
                'total_duration_ms' => round($totalDuration, 2),
                
                // Request info
                'request' => [
                    'method' => request()->method(),
                    'url' => request()->path(),
                    'full_url' => request()->fullUrl(),
                    'status_code' => http_response_code(),
                ],
                
                // Phases for waterfall header
                'phases' => $this->buildPhases($totalDuration),
                
                // All individual spans (queries, cache, http, jobs)
                'spans' => $orderedSpans,
                
                // Counts summary
                'counts' => $this->calculateCounts(),
                
                // Controller/Route info
                'route' => [
                    'name' => $this->routeName,
                    'uri' => $this->routeUri,
                    'methods' => $this->routeMethods,
                    'parameters' => $this->routeParameters ? array_keys($this->routeParameters) : [],
                ],
                
                'controller' => [
                    'class' => $this->controller,
                    'action' => $this->action,
                    'full' => $this->controller . '@' . $this->action,
                ],
                
                // Middleware
                'middleware' => [
                    'global' => $this->globalMiddleware,
                    'route' => $this->routeMiddleware,
                    'total_count' => count($this->globalMiddleware) + count($this->routeMiddleware),
                ],
                
                // Performance breakdown
                'breakdown' => $this->calculateBreakdown($totalDuration),
                
                // Memory
                'memory' => [
                    'peak_bytes' => memory_get_peak_usage(true),
                    'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'current_bytes' => memory_get_usage(true),
                    'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ],
                
                // Environment
                'environment' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'sapi' => PHP_SAPI,
                ],
                
                'timestamp' => now()->toIso8601String(),
            ];

            $this->baddybugs->record('lifecycle', 'http_request', $lifecycleData);
        });
    }

    protected function buildPhases(float $totalDuration): array
    {
        $phases = [];
        
        // Bootstrap phase
        if ($this->bootstrapEnd) {
            $bootstrapDuration = ($this->bootstrapEnd - $this->requestStart) * 1000;
            $phases[] = [
                'name' => 'BOOTSTRAP',
                'duration_ms' => round($bootstrapDuration, 2),
                'start_offset_ms' => 0,
                'percentage' => round(($bootstrapDuration / $totalDuration) * 100, 1),
            ];
        }
        
        // Middleware phase (from route match to controller start)
        if ($this->middlewareStart && $this->controllerStart) {
            $middlewareDuration = ($this->controllerStart - $this->middlewareStart) * 1000;
            $phases[] = [
                'name' => 'MIDDLEWARE',
                'duration_ms' => round($middlewareDuration, 2),
                'start_offset_ms' => round(($this->middlewareStart - $this->requestStart) * 1000, 2),
                'percentage' => round(($middlewareDuration / $totalDuration) * 100, 1),
            ];
        }
        
        // Controller phase
        if ($this->controllerStart && $this->controllerEnd) {
            $controllerDuration = ($this->controllerEnd - $this->controllerStart) * 1000;
            $phases[] = [
                'name' => 'CONTROLLER',
                'label' => $this->controller . '@' . $this->action,
                'duration_ms' => round($controllerDuration, 2),
                'start_offset_ms' => round(($this->controllerStart - $this->requestStart) * 1000, 2),
                'percentage' => round(($controllerDuration / $totalDuration) * 100, 1),
            ];
        }
        
        return $phases;
    }

    protected function buildOrderedSpans(): array
    {
        // Sort spans by start_offset_ms
        usort($this->spans, function ($a, $b) {
            return $a['start_offset_ms'] <=> $b['start_offset_ms'];
        });
        
        // Calculate depth for nesting visualization
        $orderedSpans = [];
        foreach ($this->spans as $span) {
            $depth = 0;
            
            // Calculate depth based on overlapping with previous spans
            foreach ($orderedSpans as $prevSpan) {
                $prevEnd = $prevSpan['start_offset_ms'] + $prevSpan['duration_ms'];
                if ($span['start_offset_ms'] >= $prevSpan['start_offset_ms'] && 
                    $span['start_offset_ms'] < $prevEnd) {
                    $depth = max($depth, ($prevSpan['depth'] ?? 0) + 1);
                }
            }
            
            $span['depth'] = $depth;
            $orderedSpans[] = $span;
        }
        
        return $orderedSpans;
    }

    protected function calculateCounts(): array
    {
        $counts = [
            'queries' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'cache_writes' => 0,
            'outgoing_requests' => 0,
            'jobs_dispatched' => 0,
        ];
        
        foreach ($this->spans as $span) {
            switch ($span['type']) {
                case 'QUERY':
                    $counts['queries']++;
                    break;
                case 'CACHE_HIT':
                    $counts['cache_hits']++;
                    break;
                case 'CACHE_MISS':
                    $counts['cache_misses']++;
                    break;
                case 'CACHE_WRITE':
                    $counts['cache_writes']++;
                    break;
                case 'OUTGOING_REQUEST':
                    $counts['outgoing_requests']++;
                    break;
                case 'JOB_DISPATCHED':
                    $counts['jobs_dispatched']++;
                    break;
            }
        }
        
        return $counts;
    }

    protected function calculateBreakdown(float $totalDuration): array
    {
        $breakdown = [];
        
        // Bootstrap
        if ($this->bootstrapEnd) {
            $duration = ($this->bootstrapEnd - $this->requestStart) * 1000;
            $breakdown['bootstrap'] = [
                'duration_ms' => round($duration, 2),
                'percentage' => round(($duration / $totalDuration) * 100, 1),
            ];
        }
        
        // Middleware
        if ($this->middlewareStart && $this->controllerStart) {
            $duration = ($this->controllerStart - $this->middlewareStart) * 1000;
            $breakdown['middleware'] = [
                'duration_ms' => round($duration, 2),
                'percentage' => round(($duration / $totalDuration) * 100, 1),
            ];
        }
        
        // Controller
        if ($this->controllerStart && $this->controllerEnd) {
            $duration = ($this->controllerEnd - $this->controllerStart) * 1000;
            $breakdown['controller'] = [
                'duration_ms' => round($duration, 2),
                'percentage' => round(($duration / $totalDuration) * 100, 1),
            ];
        }
        
        return $breakdown;
    }
}
