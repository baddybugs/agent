<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\Facades\BaddyBugs;
use Illuminate\Support\Facades\Event;

/**
 * UC #16: Silent Errors (Handled Exceptions)
 * 
 * Track exceptions that are caught in try/catch blocks
 */
class HandledExceptionCollector implements CollectorInterface
{
    public function boot(): void
    {
        // Helper function is now autoloaded via src/helpers.php
        // No additional setup needed
    }

    /**
     * Track a handled exception
     */
    public function trackHandledException(\Throwable $exception, array $context = []): void
    {
        $payload = [
            'message' => $exception->getMessage(),
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $this->formatStackTrace($exception->getTrace()),
            'handled' => true, // Mark as handled
            'context' => $context,
            'severity' => $this->detectSeverity($exception, $context),
        ];

        BaddyBugs::record('handled_exception', get_class($exception), $payload);
    }

    /**
     * Format stack trace
     */
    protected function formatStackTrace(array $trace): array
    {
        return array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }, array_slice($trace, 0, 10)); // Limit to 10 frames
    }

    /**
     * Detect severity of handled exception
     */
    protected function detectSeverity(\Throwable $exception, array $context): string
    {
        // Critical exceptions even if handled
        $criticalTypes = [
            'PDOException',
            'RedisException',
            'OutOfMemoryException',
        ];

        foreach ($criticalTypes as $type) {
            if ($exception instanceof $type || str_contains(get_class($exception), $type)) {
                return 'critical';
            }
        }

        // Check context
        if (isset($context['severity'])) {
            return $context['severity'];
        }

        // Default based on exception type
        if ($exception instanceof \ErrorException) {
            return 'high';
        }

        return 'medium';
    }

    /**
     * Auto-detect handled exceptions (limited capability)
     */
    public function enableAutoDetection(): void
    {
        // Register shutdown function to catch errors
        register_shutdown_function(function () {
            $error = error_get_last();
            
            if ($error && in_array($error['type'], [E_ERROR, E_WARNING, E_NOTICE])) {
                // This error might have been handled
                // We can't be sure, so we log it with a flag
                BaddyBugs::record('potential_handled_error', 'PHP Error', [
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'type' => $error['type'],
                    'possibly_handled' => true,
                ]);
            }
        });
    }
}
