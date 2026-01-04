<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Support\Facades\Event;
use Illuminate\Translation\Events\LocaleUpdated;

/**
 * Translation/Localization Collector
 * 
 * Tracks translation-related events:
 * - Missing translations
 * - Locale changes
 * - Fallback usage
 * - Translation load times
 */
class TranslationCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;
    protected array $missingTranslations = [];
    protected array $fallbacksUsed = [];

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.translations.enabled', false)) {
            return;
        }

        $this->trackMissingTranslations();
        $this->trackLocaleChanges();

        app()->terminating(function () {
            $this->sendCollectedData();
        });
    }

    protected function trackMissingTranslations(): void
    {
        if (!config('baddybugs.collectors.translations.options.track_missing', true)) {
            return;
        }

        // Hook into translator to detect missing keys
        $translator = app('translator');
        
        // Register a macro for tracking
        if (method_exists($translator, 'handleMissingTranslationKeys')) {
            $translator->handleMissingTranslationKeys(function ($key, $replace, $locale, $fallback) {
                $this->recordMissingTranslation($key, $locale, $fallback);
            });
        }

        // Alternative: Listen for translation event
        Event::listen('Illuminate\Translation\Events\MissingTranslation', function ($event) {
            $this->recordMissingTranslation($event->key, $event->locale, $event->fallback ?? null);
        });
    }

    protected function trackLocaleChanges(): void
    {
        Event::listen(LocaleUpdated::class, function (LocaleUpdated $event) {
            $this->baddybugs->record('translation', 'locale_changed', [
                'locale' => $event->locale,
                'previous_locale' => app()->getLocale(),
                'url' => request()->fullUrl(),
                'user_id' => auth()->id(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function recordMissingTranslation(string $key, string $locale, ?string $fallback): void
    {
        $hash = md5($key . $locale);
        
        if (!isset($this->missingTranslations[$hash])) {
            $this->missingTranslations[$hash] = [
                'key' => $key,
                'locale' => $locale,
                'fallback' => $fallback,
                'count' => 0,
                'locations' => [],
            ];
        }

        $this->missingTranslations[$hash]['count']++;

        // Track where this missing translation was called from
        $caller = $this->getCaller();
        if ($caller && count($this->missingTranslations[$hash]['locations']) < 3) {
            $this->missingTranslations[$hash]['locations'][] = $caller;
        }

        // Track fallback usage
        if ($fallback !== null) {
            $this->fallbacksUsed[$hash] = true;
        }
    }

    protected function getCaller(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            
            // Skip vendor and framework files
            if (!str_contains($file, 'vendor/') && str_contains($file, app_path())) {
                return basename($file) . ':' . ($frame['line'] ?? 0);
            }
        }

        return null;
    }

    protected function sendCollectedData(): void
    {
        if (!empty($this->missingTranslations)) {
            $this->baddybugs->record('translation', 'missing_keys', [
                'total_missing' => count($this->missingTranslations),
                'total_fallbacks' => count($this->fallbacksUsed),
                'keys' => array_values($this->missingTranslations),
                'url' => request()->fullUrl(),
                'locale' => app()->getLocale(),
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }
}
