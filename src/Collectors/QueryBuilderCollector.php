<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Advanced Query Builder Collector
 * 
 * Extends basic query tracking with:
 * - Query type classification (SELECT, INSERT, UPDATE, DELETE)
 * - Tables affected
 * - Chunking operations
 * - Raw queries tracking
 * - Subqueries detection
 * - Join analysis
 */
class QueryBuilderCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $queryStats = [
        'select' => 0,
        'insert' => 0,
        'update' => 0,
        'delete' => 0,
        'other' => 0,
    ];
    protected array $tablesAccessed = [];
    protected array $joinStats = [];
    protected int $subqueryCount = 0;
    protected int $chunkCount = 0;
    protected int $rawQueryCount = 0;

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.query_builder.enabled', true)) {
            return;
        }

        $this->trackQueryTypes();
        $this->trackChunking();

        app()->terminating(function () {
            $this->sendMetrics();
        });
    }

    protected function trackQueryTypes(): void
    {
        Event::listen('Illuminate\Database\Events\QueryExecuted', function ($query) {
            $this->analyzeQuery($query->sql, $query->bindings);
        });
    }

    protected function analyzeQuery(string $sql, array $bindings): void
    {
        // Determine query type
        $type = $this->getQueryType($sql);
        $this->queryStats[$type]++;

        // Extract tables
        $tables = $this->extractTables($sql);
        foreach ($tables as $table) {
            $this->tablesAccessed[$table] = ($this->tablesAccessed[$table] ?? 0) + 1;
        }

        // Detect joins
        $joins = $this->detectJoins($sql);
        if (!empty($joins)) {
            foreach ($joins as $join) {
                $key = $join['type'];
                $this->joinStats[$key] = ($this->joinStats[$key] ?? 0) + 1;
            }
        }

        // Detect subqueries
        if ($this->hasSubquery($sql)) {
            $this->subqueryCount++;
        }

        // Detect raw queries (DB::raw, selectRaw, whereRaw)
        if ($this->isRawQuery($sql, $bindings)) {
            $this->rawQueryCount++;
        }
    }

    protected function getQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));
        
        if (str_starts_with($sql, 'SELECT')) return 'select';
        if (str_starts_with($sql, 'INSERT')) return 'insert';
        if (str_starts_with($sql, 'UPDATE')) return 'update';
        if (str_starts_with($sql, 'DELETE')) return 'delete';
        
        return 'other';
    }

    protected function extractTables(string $sql): array
    {
        $tables = [];
        
        // Match FROM table
        if (preg_match('/FROM\s+["`]?(\w+)["`]?/i', $sql, $matches)) {
            $tables[] = $matches[1];
        }

        // Match INSERT INTO table
        if (preg_match('/INSERT\s+INTO\s+["`]?(\w+)["`]?/i', $sql, $matches)) {
            $tables[] = $matches[1];
        }

        // Match UPDATE table
        if (preg_match('/UPDATE\s+["`]?(\w+)["`]?/i', $sql, $matches)) {
            $tables[] = $matches[1];
        }

        // Match DELETE FROM table
        if (preg_match('/DELETE\s+FROM\s+["`]?(\w+)["`]?/i', $sql, $matches)) {
            $tables[] = $matches[1];
        }

        // Match JOIN tables
        preg_match_all('/JOIN\s+["`]?(\w+)["`]?/i', $sql, $joinMatches);
        if (!empty($joinMatches[1])) {
            $tables = array_merge($tables, $joinMatches[1]);
        }

        return array_unique($tables);
    }

    protected function detectJoins(string $sql): array
    {
        $joins = [];
        
        // Inner join
        if (preg_match_all('/\bINNER\s+JOIN\b/i', $sql, $matches)) {
            $joins[] = ['type' => 'inner', 'count' => count($matches[0])];
        }
        
        // Left join
        if (preg_match_all('/\bLEFT\s+(OUTER\s+)?JOIN\b/i', $sql, $matches)) {
            $joins[] = ['type' => 'left', 'count' => count($matches[0])];
        }
        
        // Right join
        if (preg_match_all('/\bRIGHT\s+(OUTER\s+)?JOIN\b/i', $sql, $matches)) {
            $joins[] = ['type' => 'right', 'count' => count($matches[0])];
        }
        
        // Cross join
        if (preg_match_all('/\bCROSS\s+JOIN\b/i', $sql, $matches)) {
            $joins[] = ['type' => 'cross', 'count' => count($matches[0])];
        }

        // Simple JOIN (treated as inner)
        if (preg_match_all('/\bJOIN\b(?!\s*(INNER|LEFT|RIGHT|CROSS|OUTER))/i', $sql, $matches)) {
            $joins[] = ['type' => 'simple', 'count' => count($matches[0])];
        }

        return $joins;
    }

    protected function hasSubquery(string $sql): bool
    {
        // Count opening parens with SELECT inside
        return (bool) preg_match('/\(\s*SELECT\b/i', $sql);
    }

    protected function isRawQuery(string $sql, array $bindings): bool
    {
        // Detect patterns that suggest raw SQL usage
        // These often have odd characters or complex expressions not typical of query builder
        return preg_match('/\b(COALESCE|CASE\s+WHEN|CONCAT|SUBSTRING|DATE_FORMAT|UNIX_TIMESTAMP)\b/i', $sql) ||
               preg_match('/\bAS\s+["`]?\w+_raw["`]?/i', $sql);
    }

    protected function trackChunking(): void
    {
        // Track chunk operations
        Event::listen('eloquent.retrieved:*', function () {
            // This is called for each model retrieved
            // We detect chunking by checking memory patterns
        });
    }

    /**
     * Track a chunk operation manually
     */
    public function trackChunk(int $chunkSize, int $totalProcessed): void
    {
        $this->chunkCount++;
        
        $this->baddybugs->record('query_builder', 'chunk_operation', [
            'chunk_size' => $chunkSize,
            'total_processed' => $totalProcessed,
            'chunk_number' => $this->chunkCount,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    protected function sendMetrics(): void
    {
        $totalQueries = array_sum($this->queryStats);
        
        if ($totalQueries === 0) {
            return;
        }

        // Calculate percentages
        $percentages = [];
        foreach ($this->queryStats as $type => $count) {
            $percentages[$type] = $totalQueries > 0 
                ? round(($count / $totalQueries) * 100, 1) 
                : 0;
        }

        // Top tables
        arsort($this->tablesAccessed);
        $topTables = array_slice($this->tablesAccessed, 0, 10, true);

        $this->baddybugs->record('query_builder', 'summary', [
            'total_queries' => $totalQueries,
            'query_types' => $this->queryStats,
            'query_type_percentages' => $percentages,
            'tables_accessed' => $this->tablesAccessed,
            'top_tables' => $topTables,
            'unique_tables' => count($this->tablesAccessed),
            'join_stats' => $this->joinStats,
            'total_joins' => array_sum($this->joinStats),
            'subquery_count' => $this->subqueryCount,
            'raw_query_count' => $this->rawQueryCount,
            'chunk_operations' => $this->chunkCount,
            'read_write_ratio' => $this->queryStats['select'] > 0 && 
                ($this->queryStats['insert'] + $this->queryStats['update'] + $this->queryStats['delete']) > 0
                ? round($this->queryStats['select'] / ($this->queryStats['insert'] + $this->queryStats['update'] + $this->queryStats['delete']), 2)
                : null,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
