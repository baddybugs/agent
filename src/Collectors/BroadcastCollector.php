<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Event;

/**
 * Broadcast/WebSocket Collector
 * 
 * Tracks real-time broadcast events:
 * - Event broadcasts
 * - Channel subscriptions
 * - Presence channel joins/leaves
 * - Whisper events
 * - Connection failures
 */
class BroadcastCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.broadcast.enabled', false)) {
            return;
        }

        // Skip in console - no websocket in CLI
        if (app()->runningInConsole()) {
            return;
        }

        $this->trackBroadcasts();
        $this->trackSubscriptions();
        $this->trackPresenceChannels();
    }

    protected function trackBroadcasts(): void
    {
        if (!config('baddybugs.collectors.broadcast.options.track_broadcasts', true)) {
            return;
        }

        // Track all broadcast events
        Event::listen('Illuminate\Broadcasting\BroadcastEvent', function ($event) {
            $broadcastEvent = $event->event ?? $event;
            
            $this->baddybugs->record('broadcast', 'event_broadcasted', [
                'event' => get_class($broadcastEvent),
                'channels' => method_exists($broadcastEvent, 'broadcastOn') 
                    ? $this->formatChannels($broadcastEvent->broadcastOn()) 
                    : [],
                'event_name' => method_exists($broadcastEvent, 'broadcastAs') 
                    ? $broadcastEvent->broadcastAs() 
                    : class_basename($broadcastEvent),
                'connection' => method_exists($broadcastEvent, 'broadcastConnection')
                    ? $broadcastEvent->broadcastConnection()
                    : config('broadcasting.default'),
                'user_id' => auth()->id(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function trackSubscriptions(): void
    {
        if (!config('baddybugs.collectors.broadcast.options.track_subscriptions', true)) {
            return;
        }

        // Track channel authorization attempts
        Event::listen('Illuminate\Broadcasting\Events\BroadcastChannelAuthorized', function ($event) {
            $this->baddybugs->record('broadcast', 'channel_authorized', [
                'channel' => $event->channel ?? 'unknown',
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        Event::listen('Illuminate\Broadcasting\Events\BroadcastChannelDenied', function ($event) {
            $this->baddybugs->record('broadcast', 'channel_denied', [
                'channel' => $event->channel ?? 'unknown',
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
                'severity' => 'warning',
            ]);
        });
    }

    protected function trackPresenceChannels(): void
    {
        if (!config('baddybugs.collectors.broadcast.options.track_presence', true)) {
            return;
        }

        // Listen for presence channel events
        Event::listen('pusher:*', function ($eventName, $payload) {
            if (str_contains($eventName, 'member_added') || str_contains($eventName, 'member_removed')) {
                $this->baddybugs->record('broadcast', 'presence_update', [
                    'event_type' => $eventName,
                    'channel' => $payload['channel'] ?? 'unknown',
                    'member_id' => $payload['user_id'] ?? null,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        });
    }

    /**
     * Format broadcast channels for logging
     */
    protected function formatChannels($channels): array
    {
        if (!is_array($channels)) {
            $channels = [$channels];
        }

        return array_map(function ($channel) {
            if (is_string($channel)) {
                return $channel;
            }
            if (is_object($channel) && method_exists($channel, '__toString')) {
                return (string) $channel;
            }
            if (is_object($channel) && property_exists($channel, 'name')) {
                return $channel->name;
            }
            return get_class($channel);
        }, $channels);
    }
}
