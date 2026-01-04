<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

/**
 * Session Analytics Collector
 * 
 * Tracks session-related metrics:
 * - Session duration
 * - Pages per session
 * - Session starts/ends
 * - Session regeneration
 * - Flash data usage
 */
class SessionCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected float $requestStart;

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
        $this->requestStart = microtime(true);
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.session.enabled', true)) {
            return;
        }

        $this->trackSessionMetrics();
    }

    protected function trackSessionMetrics(): void
    {
        app()->terminating(function () {
            $this->collectSessionData();
        });
    }

    protected function collectSessionData(): void
    {
        if (!Session::isStarted()) {
            return;
        }

        try {
            $sessionId = Session::getId();
            $sessionData = [
                'session_id_hash' => substr(md5($sessionId), 0, 16), // Anonymized
                'is_new_session' => $this->isNewSession(),
                'user_id' => auth()->id(),
            ];

            // Track page views per session
            $pageViewKey = 'baddybugs_page_views';
            $pageViews = (int)Session::get($pageViewKey, 0) + 1;
            Session::put($pageViewKey, $pageViews);
            $sessionData['pages_in_session'] = $pageViews;

            // Track session start time
            $sessionStartKey = 'baddybugs_session_start';
            if (!Session::has($sessionStartKey)) {
                Session::put($sessionStartKey, now()->timestamp);
                $sessionData['session_started'] = true;
            }
            
            $sessionStart = Session::get($sessionStartKey);
            $sessionData['session_duration_seconds'] = now()->timestamp - $sessionStart;

            // Track previous URL for path analysis
            $previousUrl = Session::get('_previous.url');
            if ($previousUrl) {
                $sessionData['has_navigation'] = true;
            }

            // Track flash data usage (common for form errors, notifications)
            $flashData = Session::get('_flash', []);
            if (!empty($flashData)) {
                $sessionData['has_flash_data'] = true;
                $sessionData['flash_keys'] = array_keys($flashData['old'] ?? []);
            }

            // Detect potential session issues
            if ($pageViews === 1 && !$this->isNewSession()) {
                $sessionData['potential_bounce'] = true;
            }

            // Only record periodically or on significant events
            if ($pageViews === 1 || $pageViews % 10 === 0 || isset($sessionData['session_started'])) {
                $this->baddybugs->record('session', 'analytics', $sessionData);
            }

        } catch (\Throwable $e) {
            // Silent failure
        }
    }

    protected function isNewSession(): bool
    {
        return !Session::has('baddybugs_session_start');
    }

    /**
     * Track session regeneration (security event)
     */
    public function trackRegeneration(): void
    {
        $this->baddybugs->record('session', 'regenerated', [
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Track session invalidation
     */
    public function trackInvalidation(): void
    {
        $this->baddybugs->record('session', 'invalidated', [
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
