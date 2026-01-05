<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;

/**
 * Encryption Collector
 * 
 * Tracks encryption/decryption operations:
 * - Encrypt/decrypt calls
 * - Encryption failures
 * - Decryption failures (potential tampering)
 * - Key rotation events
 * - Performance of crypto operations
 */
class EncryptionCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $operations = [];
    protected int $encryptCount = 0;
    protected int $decryptCount = 0;
    protected int $failedDecrypts = 0;
    protected float $totalEncryptTime = 0;
    protected float $totalDecryptTime = 0;

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.encryption.enabled', false)) {
            return;
        }

        $this->wrapEncrypter();

        app()->terminating(function () {
            $this->sendMetrics();
        });
    }

    /**
     * Wrap the encrypter to track operations
     */
    protected function wrapEncrypter(): void
    {
        // We can't directly hook into Crypt, so we provide manual tracking methods
        // and listen to relevant events
        
        // Track encryption failures
        Event::listen('Illuminate\Encryption\Events\EncryptionFailed', function ($event) {
            $this->trackEncryptionFailure($event);
        });

        Event::listen('Illuminate\Encryption\Events\DecryptionFailed', function ($event) {
            $this->trackDecryptionFailure($event);
        });
    }

    /**
     * Manually track an encryption operation
     * 
     * Usage in your code:
     * app(EncryptionCollector::class)->trackEncrypt('user_ssn', strlen($data));
     * $encrypted = Crypt::encrypt($data);
     */
    public function trackEncrypt(string $identifier, int $dataSize, ?float $duration = null): void
    {
        $this->encryptCount++;
        
        if ($duration) {
            $this->totalEncryptTime += $duration;
        }

        if (config('baddybugs.collectors.encryption.options.detailed', false)) {
            $this->operations[] = [
                'type' => 'encrypt',
                'identifier' => $identifier,
                'data_size' => $dataSize,
                'duration_ms' => $duration ? round($duration * 1000, 2) : null,
                'timestamp' => microtime(true),
            ];
        }
    }

    /**
     * Manually track a decryption operation
     */
    public function trackDecrypt(string $identifier, int $dataSize, ?float $duration = null): void
    {
        $this->decryptCount++;
        
        if ($duration) {
            $this->totalDecryptTime += $duration;
        }

        if (config('baddybugs.collectors.encryption.options.detailed', false)) {
            $this->operations[] = [
                'type' => 'decrypt',
                'identifier' => $identifier,
                'data_size' => $dataSize,
                'duration_ms' => $duration ? round($duration * 1000, 2) : null,
                'timestamp' => microtime(true),
            ];
        }
    }

    /**
     * Track encryption failure
     */
    protected function trackEncryptionFailure($event): void
    {
        $this->baddybugs->record('encryption', 'encrypt_failed', [
            'exception' => get_class($event->exception ?? new \Exception()),
            'message' => $event->exception?->getMessage() ?? 'Unknown error',
            'timestamp' => now()->toIso8601String(),
            'severity' => 'error',
        ]);
    }

    /**
     * Track decryption failure (potential security issue)
     */
    protected function trackDecryptionFailure($event): void
    {
        $this->failedDecrypts++;

        $this->baddybugs->record('encryption', 'decrypt_failed', [
            'exception' => get_class($event->exception ?? new \Exception()),
            'message' => $event->exception?->getMessage() ?? 'Unknown error',
            'timestamp' => now()->toIso8601String(),
            'severity' => 'warning',
            'potential_tampering' => str_contains($event->exception?->getMessage() ?? '', 'MAC'),
        ]);
    }

    /**
     * Helper to encrypt with automatic tracking
     */
    public function encryptWithTracking($value, string $identifier = 'data'): string
    {
        $start = microtime(true);
        $size = is_string($value) ? strlen($value) : strlen(serialize($value));
        
        try {
            $result = Crypt::encrypt($value);
            $this->trackEncrypt($identifier, $size, microtime(true) - $start);
            return $result;
        } catch (\Throwable $e) {
            $this->trackEncryptionFailure((object)['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Helper to decrypt with automatic tracking
     */
    public function decryptWithTracking(string $payload, string $identifier = 'data')
    {
        $start = microtime(true);
        
        try {
            $result = Crypt::decrypt($payload);
            $size = is_string($result) ? strlen($result) : strlen(serialize($result));
            $this->trackDecrypt($identifier, $size, microtime(true) - $start);
            return $result;
        } catch (\Throwable $e) {
            $this->trackDecryptionFailure((object)['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Send collected metrics
     */
    protected function sendMetrics(): void
    {
        if ($this->encryptCount === 0 && $this->decryptCount === 0) {
            return;
        }

        $data = [
            'encrypt_count' => $this->encryptCount,
            'decrypt_count' => $this->decryptCount,
            'failed_decrypts' => $this->failedDecrypts,
            'total_operations' => $this->encryptCount + $this->decryptCount,
            'avg_encrypt_time_ms' => $this->encryptCount > 0 
                ? round(($this->totalEncryptTime / $this->encryptCount) * 1000, 2) 
                : 0,
            'avg_decrypt_time_ms' => $this->decryptCount > 0 
                ? round(($this->totalDecryptTime / $this->decryptCount) * 1000, 2) 
                : 0,
            'timestamp' => now()->toIso8601String(),
        ];

        if (config('baddybugs.collectors.encryption.options.detailed', false) && !empty($this->operations)) {
            $data['operations'] = $this->operations;
        }

        $this->baddybugs->record('encryption', 'summary', $data);
    }
}
