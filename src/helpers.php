<?php

/**
 * Global helper functions for BaddyBugs Agent
 * 
 * This file is autoloaded once via composer.json
 */

if (!function_exists('reportHandledException')) {
    /**
     * Report a handled exception to BaddyBugs
     * 
     * Use this in try/catch blocks to track silent errors:
     * 
     * try {
     *     // risky operation
     * } catch (\Exception $e) {
     *     reportHandledException($e, ['context' => 'payment']);
     *     // handle error
     * }
     *
     * @param \Throwable $exception The exception that was caught
     * @param array $context Additional context (severity, custom data)
     */
    function reportHandledException(\Throwable $exception, array $context = []): void
    {
        try {
            $collector = app(\BaddyBugs\Agent\Collectors\HandledExceptionCollector::class);
            $collector->trackHandledException($exception, $context);
        } catch (\Throwable $e) {
            // Silent fail - don't break the app
        }
    }
}

if (!function_exists('baddybugs')) {
    /**
     * Get the BaddyBugs facade instance
     *
     * @return \BaddyBugs\Agent\BaddyBugs
     */
    function baddybugs(): \BaddyBugs\Agent\BaddyBugs
    {
        return app(\BaddyBugs\Agent\BaddyBugs::class);
    }
}

if (!function_exists('baddybugs_safe_request')) {
    /**
     * Safely get the current request, returns null if not available (e.g., in console)
     *
     * @return \Illuminate\Http\Request|null
     */
    function baddybugs_safe_request(): ?\Illuminate\Http\Request
    {
        try {
            if (app()->runningInConsole() && !app()->bound('request')) {
                return null;
            }
            return app('request');
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('baddybugs_request_ip')) {
    /**
     * Safely get the request IP
     *
     * @return string|null
     */
    function baddybugs_request_ip(): ?string
    {
        $request = baddybugs_safe_request();
        return $request?->ip();
    }
}

if (!function_exists('baddybugs_request_url')) {
    /**
     * Safely get the request full URL
     *
     * @return string|null
     */
    function baddybugs_request_url(): ?string
    {
        $request = baddybugs_safe_request();
        return $request?->fullUrl();
    }
}

if (!function_exists('baddybugs_request_method')) {
    /**
     * Safely get the request method
     *
     * @return string|null
     */
    function baddybugs_request_method(): ?string
    {
        $request = baddybugs_safe_request();
        return $request?->method();
    }
}

if (!function_exists('baddybugs_request_user_agent')) {
    /**
     * Safely get the request user agent
     *
     * @return string|null
     */
    function baddybugs_request_user_agent(): ?string
    {
        $request = baddybugs_safe_request();
        return $request?->userAgent();
    }
}
