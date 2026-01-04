<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Database Connection Collector
 * 
 * Tracks database connection metrics:
 * - Connection pool usage
 * - Transaction monitoring
 * - Deadlock detection
 * - Connection timeouts
 * - Slow transactions
 */
class DatabaseCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $transactions = [];
    protected array $activeConnections = [];

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.database.enabled', true)) {
            return;
        }

        $this->trackTransactions();
        $this->trackConnections();

        app()->terminating(function () {
            $this->collectConnectionMetrics();
        });
    }

    protected function trackTransactions(): void
    {
        if (!config('baddybugs.collectors.database.options.track_transactions', true)) {
            return;
        }

        Event::listen('Illuminate\Database\Events\TransactionBeginning', function ($event) {
            $connectionName = $event->connectionName;
            $this->transactions[$connectionName] = [
                'started_at' => microtime(true),
                'connection' => $connectionName,
            ];
        });

        Event::listen('Illuminate\Database\Events\TransactionCommitted', function ($event) {
            $this->recordTransactionEnd($event->connectionName, 'committed');
        });

        Event::listen('Illuminate\Database\Events\TransactionRolledBack', function ($event) {
            $this->recordTransactionEnd($event->connectionName, 'rolled_back');
        });
    }

    protected function recordTransactionEnd(string $connectionName, string $status): void
    {
        if (!isset($this->transactions[$connectionName])) {
            return;
        }

        $transaction = $this->transactions[$connectionName];
        $duration = (microtime(true) - $transaction['started_at']) * 1000;
        $threshold = config('baddybugs.collectors.database.options.transaction_threshold_ms', 5000);

        $data = [
            'connection' => $connectionName,
            'status' => $status,
            'duration_ms' => round($duration, 2),
            'is_slow' => $duration > $threshold,
        ];

        // Only record slow transactions or rollbacks
        if ($duration > $threshold || $status === 'rolled_back') {
            $data['severity'] = $status === 'rolled_back' ? 'warning' : 'info';
            $this->baddybugs->record('database', 'transaction', $data);
        }

        unset($this->transactions[$connectionName]);
    }

    protected function trackConnections(): void
    {
        if (!config('baddybugs.collectors.database.options.track_connection_pool', true)) {
            return;
        }

        // Track connection events
        Event::listen('Illuminate\Database\Events\ConnectionEstablished', function ($event) {
            $this->activeConnections[$event->connectionName] = [
                'established_at' => microtime(true),
            ];
        });
    }

    protected function collectConnectionMetrics(): void
    {
        try {
            $metrics = [
                'active_connections' => count($this->activeConnections),
                'open_transactions' => count($this->transactions),
                'timestamp' => now()->toIso8601String(),
            ];

            // Try to get connection info from database
            $driver = config('database.default');
            
            if ($driver === 'pgsql') {
                $metrics['pool'] = $this->getPostgresPoolInfo();
            } elseif ($driver === 'mysql') {
                $metrics['pool'] = $this->getMysqlPoolInfo();
            }

            // Detect potential issues
            if (count($this->transactions) > 0) {
                foreach ($this->transactions as $conn => $tx) {
                    $duration = (microtime(true) - $tx['started_at']) * 1000;
                    if ($duration > 30000) { // 30 seconds
                        $metrics['long_running_transactions'][] = [
                            'connection' => $conn,
                            'duration_ms' => round($duration, 2),
                        ];
                    }
                }
            }

            $this->baddybugs->record('database', 'connection_metrics', $metrics);
        } catch (\Throwable $e) {
            // Silent failure
        }
    }

    protected function getPostgresPoolInfo(): array
    {
        try {
            $result = DB::select("SELECT count(*) as total, 
                                         sum(case when state = 'active' then 1 else 0 end) as active,
                                         sum(case when state = 'idle' then 1 else 0 end) as idle
                                  FROM pg_stat_activity 
                                  WHERE datname = current_database()");
            
            if (!empty($result)) {
                return [
                    'total' => $result[0]->total,
                    'active' => $result[0]->active,
                    'idle' => $result[0]->idle,
                ];
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return [];
    }

    protected function getMysqlPoolInfo(): array
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $maxResult = DB::select("SHOW VARIABLES LIKE 'max_connections'");
            
            return [
                'connected' => $result[0]->Value ?? 0,
                'max_connections' => $maxResult[0]->Value ?? 0,
            ];
        } catch (\Throwable $e) {
            // Ignore
        }

        return [];
    }

    /**
     * Detect potential deadlock (call from exception handler)
     */
    public function trackDeadlock(\Throwable $exception): void
    {
        $message = $exception->getMessage();
        
        if (str_contains($message, 'deadlock') || str_contains($message, 'lock wait timeout')) {
            $this->baddybugs->record('database', 'deadlock', [
                'message' => $message,
                'exception' => get_class($exception),
                'open_transactions' => count($this->transactions),
                'timestamp' => now()->toIso8601String(),
                'severity' => 'critical',
            ]);
        }
    }
}
