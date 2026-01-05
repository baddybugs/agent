<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Hashing Collector
 * 
 * Tracks password hashing and verification:
 * - Hash creation
 * - Hash verification (check)
 * - Failed verifications
 * - Hash algorithm usage
 * - Rehash needs detection
 */
class HashingCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected int $hashCount = 0;
    protected int $checkCount = 0;
    protected int $failedChecks = 0;
    protected int $rehashNeeded = 0;
    protected array $algorithmUsage = [];

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.hashing.enabled', false)) {
            return;
        }

        app()->terminating(function () {
            $this->sendMetrics();
        });
    }

    /**
     * Track a hash creation
     */
    public function trackHash(string $algorithm = 'bcrypt'): void
    {
        $this->hashCount++;
        $this->algorithmUsage[$algorithm] = ($this->algorithmUsage[$algorithm] ?? 0) + 1;
    }

    /**
     * Track a hash verification
     */
    public function trackCheck(bool $passed, bool $needsRehash = false): void
    {
        $this->checkCount++;
        
        if (!$passed) {
            $this->failedChecks++;
        }
        
        if ($needsRehash) {
            $this->rehashNeeded++;
        }
    }

    /**
     * Helper to hash with tracking
     */
    public function makeWithTracking(string $value, array $options = []): string
    {
        $algorithm = config('hashing.driver', 'bcrypt');
        $this->trackHash($algorithm);
        
        return Hash::make($value, $options);
    }

    /**
     * Helper to check hash with tracking
     */
    public function checkWithTracking(string $value, string $hashedValue): bool
    {
        $result = Hash::check($value, $hashedValue);
        $needsRehash = Hash::needsRehash($hashedValue);
        
        $this->trackCheck($result, $needsRehash);
        
        return $result;
    }

    protected function sendMetrics(): void
    {
        if ($this->hashCount === 0 && $this->checkCount === 0) {
            return;
        }

        $this->baddybugs->record('hashing', 'summary', [
            'hash_count' => $this->hashCount,
            'check_count' => $this->checkCount,
            'failed_checks' => $this->failedChecks,
            'success_rate' => $this->checkCount > 0 
                ? round((($this->checkCount - $this->failedChecks) / $this->checkCount) * 100, 1) 
                : 100,
            'rehash_needed' => $this->rehashNeeded,
            'algorithm_usage' => $this->algorithmUsage,
            'primary_algorithm' => config('hashing.driver', 'bcrypt'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
