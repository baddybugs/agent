<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Support\Facades\Storage;

/**
 * Filesystem Collector
 * 
 * Tracks filesystem operations:
 * - File reads/writes
 * - Storage disk usage
 * - Slow I/O operations
 * - Upload/download tracking
 */
class FilesystemCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $operations = [];

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.filesystem.enabled', false)) {
            return;
        }

        app()->terminating(function () {
            $this->collectDiskUsage();
        });
    }

    /**
     * Track a file read operation
     */
    public function trackRead(string $path, int $size, float $durationMs, string $disk = 'local'): void
    {
        $this->operations[] = [
            'type' => 'read',
            'path' => $this->sanitizePath($path),
            'disk' => $disk,
            'size_bytes' => $size,
            'duration_ms' => round($durationMs, 2),
            'is_slow' => $durationMs > config('baddybugs.collectors.filesystem.options.slow_threshold_ms', 100),
        ];

        if (count($this->operations) >= 50) {
            $this->flush();
        }
    }

    /**
     * Track a file write operation
     */
    public function trackWrite(string $path, int $size, float $durationMs, string $disk = 'local'): void
    {
        $this->operations[] = [
            'type' => 'write',
            'path' => $this->sanitizePath($path),
            'disk' => $disk,
            'size_bytes' => $size,
            'duration_ms' => round($durationMs, 2),
            'is_slow' => $durationMs > config('baddybugs.collectors.filesystem.options.slow_threshold_ms', 100),
        ];

        if (count($this->operations) >= 50) {
            $this->flush();
        }
    }

    /**
     * Track a file delete operation
     */
    public function trackDelete(string $path, string $disk = 'local'): void
    {
        $this->operations[] = [
            'type' => 'delete',
            'path' => $this->sanitizePath($path),
            'disk' => $disk,
        ];
    }

    protected function collectDiskUsage(): void
    {
        if (!config('baddybugs.collectors.filesystem.options.track_disk_usage', true)) {
            return;
        }

        try {
            $disks = config('baddybugs.collectors.filesystem.options.disks', ['local', 'public']);
            $usage = [];

            foreach ($disks as $diskName) {
                try {
                    $disk = Storage::disk($diskName);
                    $path = $disk->path('');
                    
                    if (is_dir($path)) {
                        $usage[$diskName] = [
                            'free_bytes' => disk_free_space($path),
                            'total_bytes' => disk_total_space($path),
                            'used_bytes' => disk_total_space($path) - disk_free_space($path),
                            'usage_percentage' => round(((disk_total_space($path) - disk_free_space($path)) / disk_total_space($path)) * 100, 2),
                        ];
                    }
                } catch (\Throwable $e) {
                    // Skip this disk
                }
            }

            if (!empty($usage)) {
                $this->baddybugs->record('filesystem', 'disk_usage', [
                    'disks' => $usage,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        } catch (\Throwable $e) {
            // Silent failure
        }

        // Flush any pending operations
        $this->flush();
    }

    protected function flush(): void
    {
        if (empty($this->operations)) {
            return;
        }

        $summary = [
            'operation_count' => count($this->operations),
            'reads' => count(array_filter($this->operations, fn($o) => $o['type'] === 'read')),
            'writes' => count(array_filter($this->operations, fn($o) => $o['type'] === 'write')),
            'deletes' => count(array_filter($this->operations, fn($o) => $o['type'] === 'delete')),
            'total_bytes_read' => array_sum(array_column(array_filter($this->operations, fn($o) => $o['type'] === 'read'), 'size_bytes')),
            'total_bytes_written' => array_sum(array_column(array_filter($this->operations, fn($o) => $o['type'] === 'write'), 'size_bytes')),
            'slow_operations' => count(array_filter($this->operations, fn($o) => $o['is_slow'] ?? false)),
            'operations' => array_slice($this->operations, 0, 20), // Limit detailed operations
            'timestamp' => now()->toIso8601String(),
        ];

        $this->baddybugs->record('filesystem', 'operations', $summary);
        $this->operations = [];
    }

    protected function sanitizePath(string $path): string
    {
        // Remove base path for privacy
        $basePath = base_path();
        if (str_starts_with($path, $basePath)) {
            return substr($path, strlen($basePath));
        }
        return $path;
    }
}
