<?php

namespace BaddyBugs\Agent\Buffers;

class FileBuffer implements BufferInterface
{
    protected string $path;
    protected string $filename;
    
    /**
     * Maximum file size in bytes before rotation (default: 10MB)
     */
    protected int $maxFileSize;
    
    /**
     * TTL in seconds - files older than this are auto-deleted (default: 24h)
     */
    protected int $ttlSeconds;

    public function __construct()
    {
        $this->path = config('baddybugs.storage_path', storage_path('baddybugs/buffer'));
        $this->filename = $this->path . '/events.jsonl';
        $this->maxFileSize = config('baddybugs.buffer_max_size', 10 * 1024 * 1024); // 10MB
        $this->ttlSeconds = config('baddybugs.buffer_ttl', 60 * 60); // 1 hour fallback
        
        if (!file_exists($this->path)) {
            if (!@mkdir($this->path, 0755, true)) {
                \Illuminate\Support\Facades\Log::error("BaddyBugs: Failed to create buffer directory: {$this->path}. Check permissions.");
            }
        }
        
        // Auto-cleanup old files (runs occasionally, not every request)
        $this->maybeCleanup();
    }

    public function push(array $entry): void
    {
        // Rotate if file is too big
        if ($this->shouldRotate()) {
            $this->rotate();
        }
        
        // Write the event
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            try {
                // Remove silence operator to detect errors
                $result = file_put_contents($this->filename, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
                
                if ($result === false) {
                    \Illuminate\Support\Facades\Log::error("BaddyBugs: Failed to write to buffer file: {$this->filename}. Check permissions.");
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("BaddyBugs FileBuffer Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if current file should be rotated
     */
    protected function shouldRotate(): bool
    {
        if (!file_exists($this->filename)) {
            return false;
        }
        
        $size = @filesize($this->filename);
        return $size !== false && $size >= $this->maxFileSize;
    }

    /**
     * Rotate the current file
     */
    protected function rotate(): void
    {
        $rotatedName = $this->path . '/events_' . time() . '.jsonl';
        @rename($this->filename, $rotatedName);
    }

    /**
     * Cleanup old files (runs ~1% of requests to minimize overhead)
     */
    protected function maybeCleanup(): void
    {
        // Only run cleanup occasionally (1% of requests)
        if (mt_rand(1, 100) > 1) {
            return;
        }
        
        $this->cleanup();
    }

    /**
     * Delete files older than TTL
     */
    public function cleanup(): int
    {
        $deleted = 0;
        $cutoff = time() - $this->ttlSeconds;
        
        $files = glob($this->path . '/events*.jsonl') ?: [];
        
        foreach ($files as $file) {
            // Don't delete the main events.jsonl
            if (basename($file) === 'events.jsonl') {
                continue;
            }
            
            // Delete if older than TTL
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                @unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Get total buffer size (for monitoring)
     */
    public function getTotalSize(): int
    {
        $total = 0;
        $files = glob($this->path . '/events*.jsonl') ?: [];
        
        foreach ($files as $file) {
            $size = @filesize($file);
            if ($size !== false) {
                $total += $size;
            }
        }
        
        return $total;
    }

    public function flush(): array
    {
        return [];
    }
}
