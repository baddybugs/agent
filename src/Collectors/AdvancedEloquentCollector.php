<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Advanced Eloquent Collector
 * 
 * Deep Eloquent ORM tracking:
 * - Global scopes application
 * - Local scopes usage
 * - Accessor/Mutator calls
 * - Attribute casting
 * - Soft deletes
 * - Factory usage
 * - Mass assignment
 * - Hidden/Visible attributes
 */
class AdvancedEloquentCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $scopeUsage = [];
    protected array $castUsage = [];
    protected array $accessorUsage = [];
    protected array $mutatorUsage = [];
    protected array $softDeleteUsage = [];
    protected array $massAssignments = [];
    protected int $factoryUsageCount = 0;

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.advanced_eloquent.enabled', false)) {
            return;
        }

        $this->trackScopes();
        $this->trackSoftDeletes();
        $this->trackMassAssignment();
        $this->trackFactoryUsage();

        app()->terminating(function () {
            $this->sendMetrics();
        });
    }

    protected function trackScopes(): void
    {
        // Track when scopes are applied by listening to query events
        Event::listen('Illuminate\Database\Events\QueryExecuted', function ($query) {
            $this->detectScopeUsage($query->sql);
        });
    }

    protected function detectScopeUsage(string $sql): void
    {
        // Detect common scope patterns
        $scopePatterns = [
            'active' => '/\bwhere.*?\bactive\b.*?=\s*(1|true)/i',
            'published' => '/\bwhere.*?\bpublished(_at)?\b/i',
            'withTrashed' => '/\bdeleted_at\s+is\s+(not\s+)?null/i',
            'onlyTrashed' => '/\bdeleted_at\s+is\s+not\s+null/i',
            'ordered' => '/\border\s+by/i',
            'limited' => '/\blimit\s+\d+/i',
            'whereNull' => '/\bwhere.*?\bis\s+null/i',
            'whereNotNull' => '/\bwhere.*?\bis\s+not\s+null/i',
        ];

        foreach ($scopePatterns as $scope => $pattern) {
            if (preg_match($pattern, $sql)) {
                $this->scopeUsage[$scope] = ($this->scopeUsage[$scope] ?? 0) + 1;
            }
        }
    }

    protected function trackSoftDeletes(): void
    {
        // Track soft delete operations
        Event::listen('eloquent.deleted:*', function ($eventName, $data) {
            $model = $data[0] ?? null;
            
            if ($model instanceof Model && $this->usesSoftDeletes($model)) {
                $modelClass = class_basename(get_class($model));
                $this->softDeleteUsage[$modelClass] = ($this->softDeleteUsage[$modelClass] ?? 0) + 1;
            }
        });

        Event::listen('eloquent.restored:*', function ($eventName, $data) {
            $model = $data[0] ?? null;
            
            if ($model instanceof Model) {
                $modelClass = class_basename(get_class($model));
                $key = "{$modelClass}:restored";
                $this->softDeleteUsage[$key] = ($this->softDeleteUsage[$key] ?? 0) + 1;
            }
        });

        Event::listen('eloquent.forceDeleted:*', function ($eventName, $data) {
            $model = $data[0] ?? null;
            
            if ($model instanceof Model) {
                $modelClass = class_basename(get_class($model));
                $key = "{$modelClass}:forceDeleted";
                $this->softDeleteUsage[$key] = ($this->softDeleteUsage[$key] ?? 0) + 1;
            }
        });
    }

    protected function usesSoftDeletes(Model $model): bool
    {
        return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model));
    }

    protected function trackMassAssignment(): void
    {
        // Track mass assignment via created/updated events
        Event::listen('eloquent.created:*', function ($eventName, $data) {
            $this->detectMassAssignment('create', $data);
        });

        Event::listen('eloquent.updated:*', function ($eventName, $data) {
            $this->detectMassAssignment('update', $data);
        });
    }

    protected function detectMassAssignment(string $operation, array $data): void
    {
        $model = $data[0] ?? null;
        
        if (!$model instanceof Model) {
            return;
        }

        $modelClass = class_basename(get_class($model));
        
        // Track fillable usage
        $fillable = $model->getFillable();
        $guarded = $model->getGuarded();
        
        $key = $modelClass;
        if (!isset($this->massAssignments[$key])) {
            $this->massAssignments[$key] = [
                'model' => $modelClass,
                'fillable_count' => count($fillable),
                'guarded_count' => count($guarded),
                'is_guarded_all' => $guarded === ['*'],
                'is_unguarded' => empty($guarded),
                'operations' => ['create' => 0, 'update' => 0],
            ];
        }
        
        $this->massAssignments[$key]['operations'][$operation]++;
    }

    protected function trackFactoryUsage(): void
    {
        // Listen for factory-related events (testing)
        Event::listen('eloquent.created:*', function ($eventName, $data) {
            $model = $data[0] ?? null;
            
            if ($model instanceof Model && $this->wasCreatedByFactory($model)) {
                $this->factoryUsageCount++;
            }
        });
    }

    protected function wasCreatedByFactory(Model $model): bool
    {
        // Check if model has factory attribute marker
        // This is a heuristic - factories often set this
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        
        foreach ($trace as $frame) {
            if (isset($frame['class']) && str_contains($frame['class'], 'Factory')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Track accessor usage manually
     */
    public function trackAccessor(string $model, string $accessor): void
    {
        $key = "{$model}:{$accessor}";
        $this->accessorUsage[$key] = ($this->accessorUsage[$key] ?? 0) + 1;
    }

    /**
     * Track mutator usage manually
     */
    public function trackMutator(string $model, string $mutator): void
    {
        $key = "{$model}:{$mutator}";
        $this->mutatorUsage[$key] = ($this->mutatorUsage[$key] ?? 0) + 1;
    }

    /**
     * Track cast usage
     */
    public function trackCast(string $model, string $attribute, string $castType): void
    {
        if (!isset($this->castUsage[$castType])) {
            $this->castUsage[$castType] = [];
        }
        
        $key = "{$model}.{$attribute}";
        if (!in_array($key, $this->castUsage[$castType])) {
            $this->castUsage[$castType][] = $key;
        }
    }

    /**
     * Analyze a model's attribute definitions
     */
    public function analyzeModel(Model $model): array
    {
        $class = get_class($model);
        $reflection = new \ReflectionClass($model);
        
        $analysis = [
            'model' => class_basename($class),
            'full_class' => $class,
            'table' => $model->getTable(),
            'primary_key' => $model->getKeyName(),
            'incrementing' => $model->getIncrementing(),
            'timestamps' => $model->usesTimestamps(),
            'soft_deletes' => $this->usesSoftDeletes($model),
            'fillable' => $model->getFillable(),
            'guarded' => $model->getGuarded(),
            'hidden' => $model->getHidden(),
            'visible' => $model->getVisible(),
            'casts' => $model->getCasts(),
            'appends' => $model->getAppends() ?? [],
            'dates' => $model->getDates(),
            'connection' => $model->getConnectionName(),
        ];

        // Detect accessors and mutators
        $accessors = [];
        $mutators = [];
        
        foreach ($reflection->getMethods() as $method) {
            $name = $method->getName();
            
            // Old style: getXxxAttribute
            if (preg_match('/^get(.+)Attribute$/', $name, $matches)) {
                $accessors[] = lcfirst($matches[1]);
            }
            
            // Old style: setXxxAttribute
            if (preg_match('/^set(.+)Attribute$/', $name, $matches)) {
                $mutators[] = lcfirst($matches[1]);
            }
        }
        
        $analysis['accessors'] = $accessors;
        $analysis['mutators'] = $mutators;
        
        return $analysis;
    }

    protected function sendMetrics(): void
    {
        $hasData = !empty($this->scopeUsage) || 
                   !empty($this->softDeleteUsage) || 
                   !empty($this->massAssignments);

        if (!$hasData) {
            return;
        }

        $this->baddybugs->record('advanced_eloquent', 'summary', [
            'scope_usage' => $this->scopeUsage,
            'soft_delete_operations' => $this->softDeleteUsage,
            'mass_assignments' => $this->massAssignments,
            'accessor_calls' => count($this->accessorUsage),
            'mutator_calls' => count($this->mutatorUsage),
            'cast_types_used' => array_keys($this->castUsage),
            'factory_creations' => $this->factoryUsageCount,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
