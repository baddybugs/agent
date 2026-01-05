<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Observer Collector
 * 
 * Tracks Eloquent Observer activity:
 * - Which observers are registered
 * - Observer method calls
 * - Observer execution time
 * - Observer failures
 */
class ObserverCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $observerCalls = [];
    protected array $observersRegistered = [];
    protected array $observerTiming = [];

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.observers.enabled', false)) {
            return;
        }

        $this->detectRegisteredObservers();
        $this->trackObserverCalls();

        app()->terminating(function () {
            $this->sendMetrics();
        });
    }

    /**
     * Detect registered observers for each model
     */
    protected function detectRegisteredObservers(): void
    {
        // This runs after models are loaded
        app()->booted(function () {
            $this->scanForObservers();
        });
    }

    protected function scanForObservers(): void
    {
        // Get all registered models from the observer dispatcher
        try {
            $dispatcher = Model::getEventDispatcher();
            
            if ($dispatcher && method_exists($dispatcher, 'getListeners')) {
                $listeners = $dispatcher->getListeners('eloquent.*');
                
                foreach ($listeners as $event => $handlers) {
                    foreach ($handlers as $handler) {
                        if (is_array($handler) && isset($handler[0])) {
                            $class = is_object($handler[0]) ? get_class($handler[0]) : $handler[0];
                            $this->observersRegistered[$class] = $this->observersRegistered[$class] ?? [];
                            $this->observersRegistered[$class][] = $event;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silent fail
        }
    }

    protected function trackObserverCalls(): void
    {
        $observerEvents = [
            'creating', 'created',
            'updating', 'updated',
            'saving', 'saved',
            'deleting', 'deleted',
            'restoring', 'restored',
            'forceDeleting', 'forceDeleted',
            'replicating',
            'retrieved',
        ];

        foreach ($observerEvents as $event) {
            Event::listen("eloquent.{$event}:*", function ($eventName, $data) use ($event) {
                $this->recordObserverCall($event, $eventName, $data);
            });
        }
    }

    protected function recordObserverCall(string $event, string $eventName, array $data): void
    {
        $model = $data[0] ?? null;
        
        if (!$model instanceof Model) {
            return;
        }

        $modelClass = get_class($model);
        $key = "{$modelClass}:{$event}";
        
        if (!isset($this->observerCalls[$key])) {
            $this->observerCalls[$key] = [
                'model' => $modelClass,
                'event' => $event,
                'count' => 0,
            ];
        }
        
        $this->observerCalls[$key]['count']++;
    }

    /**
     * Manually track observer execution time
     * 
     * Usage:
     * $collector = app(ObserverCollector::class);
     * $collector->startObserver('UserObserver', 'created');
     * // ... observer logic ...
     * $collector->endObserver('UserObserver', 'created');
     */
    public function startObserver(string $observer, string $method): void
    {
        $key = "{$observer}:{$method}";
        $this->observerTiming[$key] = [
            'start' => microtime(true),
            'observer' => $observer,
            'method' => $method,
        ];
    }

    public function endObserver(string $observer, string $method): void
    {
        $key = "{$observer}:{$method}";
        
        if (isset($this->observerTiming[$key])) {
            $duration = (microtime(true) - $this->observerTiming[$key]['start']) * 1000;
            
            $this->observerTiming[$key]['duration_ms'] = round($duration, 2);
            $this->observerTiming[$key]['end'] = microtime(true);
            
            // Record if slow (> 50ms)
            if ($duration > 50) {
                $this->baddybugs->record('observer', 'slow_observer', [
                    'observer' => $observer,
                    'method' => $method,
                    'duration_ms' => round($duration, 2),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        }
    }

    /**
     * Track observer failure
     */
    public function trackObserverFailure(string $observer, string $method, \Throwable $exception): void
    {
        $this->baddybugs->record('observer', 'observer_failed', [
            'observer' => $observer,
            'method' => $method,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'timestamp' => now()->toIso8601String(),
            'severity' => 'error',
        ]);
    }

    protected function sendMetrics(): void
    {
        if (empty($this->observerCalls)) {
            return;
        }

        // Group by model
        $byModel = [];
        foreach ($this->observerCalls as $call) {
            $model = class_basename($call['model']);
            if (!isset($byModel[$model])) {
                $byModel[$model] = [];
            }
            $byModel[$model][$call['event']] = $call['count'];
        }

        // Group by event type
        $byEvent = [];
        foreach ($this->observerCalls as $call) {
            $event = $call['event'];
            $byEvent[$event] = ($byEvent[$event] ?? 0) + $call['count'];
        }

        // Calculate timing stats
        $timingStats = [];
        foreach ($this->observerTiming as $key => $timing) {
            if (isset($timing['duration_ms'])) {
                $timingStats[] = [
                    'observer' => $timing['observer'],
                    'method' => $timing['method'],
                    'duration_ms' => $timing['duration_ms'],
                ];
            }
        }

        $this->baddybugs->record('observer', 'summary', [
            'total_calls' => array_sum(array_column($this->observerCalls, 'count')),
            'unique_model_events' => count($this->observerCalls),
            'registered_observers' => array_keys($this->observersRegistered),
            'calls_by_model' => $byModel,
            'calls_by_event' => $byEvent,
            'timing_samples' => array_slice($timingStats, 0, 20),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
