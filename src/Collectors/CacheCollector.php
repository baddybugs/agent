<?php

namespace BaddyBugs\Agent\Collectors;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;
use BaddyBugs\Agent\Facades\BaddyBugs;

class CacheCollector implements CollectorInterface
{
    public function boot(): void
    {
        Event::listen(CacheHit::class, function ($event) {
            $this->record('hit', $event->key, $event->storeName ?? 'default', $event->tags ?? [], $event->value);
        });

        Event::listen(CacheMissed::class, function ($event) {
            $this->record('miss', $event->key, $event->storeName ?? 'default', $event->tags ?? []);
        });

        Event::listen(KeyWritten::class, function ($event) {
            $this->record('write', $event->key, $event->storeName ?? 'default', $event->tags ?? [], $event->value, $event->seconds);
        });

        Event::listen(KeyForgotten::class, function ($event) {
            $this->record('forget', $event->key, $event->storeName ?? 'default', $event->tags ?? []);
        });
    }

    protected function record(string $action, string $key, string $store, array $tags, $value = null, $seconds = null): void
    {
        // Don't record internal telescope/baddybugs/laravel framework keys
        if (str_contains($key, 'baddybugs') || 
            str_contains($key, 'telescope') ||
            str_contains($key, 'illuminate:') ||
            str_contains($key, 'laravel_cache')) {
            return;
        }

        // Calculate value size if available
        $size = null;
        $valuePreview = null;
        if ($value !== null) {
            try {
                $serialized = serialize($value);
                $size = strlen($serialized);
                
                // Create a preview of the value (for debugging)
                if (is_scalar($value)) {
                    $valuePreview = (string) $value;
                } elseif (is_array($value)) {
                    $valuePreview = 'Array(' . count($value) . ' items)';
                } elseif (is_object($value)) {
                    $valuePreview = get_class($value);
                }
                
                // Truncate preview if too long
                if ($valuePreview && strlen($valuePreview) > 100) {
                    $valuePreview = substr($valuePreview, 0, 100) . '...';
                }
            } catch (\Throwable $e) {
                // Cannot serialize, that's fine
            }
        }

        BaddyBugs::record('cache', $key, [
            'action' => $action,
            'store' => $store,
            'tags' => $tags,
            'expiration' => $seconds,
            'ttl' => $seconds,
            'size' => $size,
            'value_preview' => $valuePreview,
            'value_type' => $value !== null ? gettype($value) : null,
        ]);
    }
}
