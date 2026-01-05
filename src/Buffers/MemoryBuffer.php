<?php

namespace BaddyBugs\Agent\Buffers;

use BaddyBugs\Agent\Sender\SenderInterface;

class MemoryBuffer implements BufferInterface
{
    protected array $buffer = [];
    protected SenderInterface $sender;
    protected bool $sendFailed = false;

    public function __construct(SenderInterface $sender)
    {
        $this->sender = $sender;
    }

    public function push(array $entry): void
    {
        // Safety: Prevent unlimited memory growth
        if (count($this->buffer) >= 5000) {
            // Fallback to file buffer to avoid losing data
            $this->fallbackToFile($entry);
            return;
        }

        $this->buffer[] = $entry;
    }

    public function flush(): array
    {
        $items = $this->buffer;
        $this->buffer = [];
        return $items;
    }

    /**
     * Send buffered events - ONLY called from terminate() after response is sent
     * If this fails, events are NOT lost - they go to file buffer
     */
    public function flushAndSend(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $events = $this->flush();
        
        try {
            // Attempt to send - this happens AFTER response is sent to user
            if (!$this->sender->send($events)) {
                // Failed to send - fallback to file buffer
                $this->fallbackToFileMultiple($events);
            }
        } catch (\Throwable $e) {
            // Any error - fallback to file buffer
            $this->fallbackToFileMultiple($events);
        }
    }

    /**
     * Fallback: write events to file buffer for later processing
     */
    protected function fallbackToFile(array $entry): void
    {
        try {
            $path = config('baddybugs.storage_path', storage_path('baddybugs/buffer'));
            if (!file_exists($path)) {
                @mkdir($path, 0755, true);
            }
            $filename = $path . '/events.jsonl';
            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line !== false) {
                @file_put_contents($filename, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $e) {
            // Silently ignore - monitoring should never break the app
        }
    }

    /**
     * Fallback: write multiple events to file buffer
     */
    protected function fallbackToFileMultiple(array $events): void
    {
        foreach ($events as $event) {
            $this->fallbackToFile($event);
        }
    }
    
    public function __destruct()
    {
        // DON'T send in destructor - this can block during shutdown
        // Instead, fallback any remaining events to file buffer
        if (!empty($this->buffer)) {
            $this->fallbackToFileMultiple($this->flush());
        }
    }
}
