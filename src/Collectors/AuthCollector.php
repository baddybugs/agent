<?php

namespace BaddyBugs\Agent\Collectors;

use BaddyBugs\Agent\BaddyBugs;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;

/**
 * Authentication Collector
 * 
 * Tracks all authentication-related events:
 * - Login/Logout
 * - Failed attempts
 * - Lockouts
 * - Password resets
 * - Registration
 * - Email verification
 * - 2FA events
 * - Impersonation
 */
class AuthCollector implements CollectorInterface
{
    protected BaddyBugs $baddybugs;

    public function __construct(BaddyBugs $baddybugs)
    {
        $this->baddybugs = $baddybugs;
    }

    public function boot(): void
    {
        if (!config('baddybugs.collectors.auth.enabled', true)) {
            return;
        }

        $this->trackLogins();
        $this->trackLogouts();
        $this->trackFailedAttempts();
        $this->trackLockouts();
        $this->trackPasswordResets();
        $this->trackRegistrations();
        $this->trackVerifications();
        $this->track2FA();
        $this->trackImpersonation();
    }

    protected function trackLogins(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_logins', true)) {
            return;
        }

        Event::listen(Login::class, function (Login $event) {
            $this->baddybugs->record('auth', 'login', [
                'user_id' => $event->user->getAuthIdentifier(),
                'guard' => $event->guard,
                'remember' => $event->remember,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function trackLogouts(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_logouts', true)) {
            return;
        }

        Event::listen(Logout::class, function (Logout $event) {
            $this->baddybugs->record('auth', 'logout', [
                'user_id' => $event->user ? $event->user->getAuthIdentifier() : null,
                'guard' => $event->guard,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        Event::listen(OtherDeviceLogout::class, function (OtherDeviceLogout $event) {
            $this->baddybugs->record('auth', 'logout_other_devices', [
                'user_id' => $event->user->getAuthIdentifier(),
                'guard' => $event->guard,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function trackFailedAttempts(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_failed_attempts', true)) {
            return;
        }

        Event::listen(Failed::class, function (Failed $event) {
            $this->baddybugs->record('auth', 'login_failed', [
                'guard' => $event->guard,
                'credentials' => $this->sanitizeCredentials($event->credentials),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toIso8601String(),
                'severity' => 'warning',
            ]);
        });

        Event::listen(Attempting::class, function (Attempting $event) {
            // Track attempts for security analysis
            $this->baddybugs->record('auth', 'login_attempt', [
                'guard' => $event->guard,
                'remember' => $event->remember,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function trackLockouts(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_lockouts', true)) {
            return;
        }

        Event::listen(Lockout::class, function (Lockout $event) {
            $this->baddybugs->record('auth', 'lockout', [
                'ip' => request()->ip(),
                'email' => $event->request->input('email'),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toIso8601String(),
                'severity' => 'high',
            ]);
        });
    }

    protected function trackPasswordResets(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_password_resets', true)) {
            return;
        }

        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            $this->baddybugs->record('auth', 'password_reset', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function trackRegistrations(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_registrations', true)) {
            return;
        }

        Event::listen(Registered::class, function (Registered $event) {
            $this->baddybugs->record('auth', 'registered', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function trackVerifications(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_verifications', true)) {
            return;
        }

        Event::listen(Verified::class, function (Verified $event) {
            $this->baddybugs->record('auth', 'email_verified', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function track2FA(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_2fa', true)) {
            return;
        }

        // Laravel Fortify 2FA events
        Event::listen('TwoFactorAuthenticationChallenged', function ($event) {
            $this->baddybugs->record('auth', '2fa_challenged', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        Event::listen('TwoFactorAuthenticationEnabled', function ($event) {
            $this->baddybugs->record('auth', '2fa_enabled', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        Event::listen('TwoFactorAuthenticationDisabled', function ($event) {
            $this->baddybugs->record('auth', '2fa_disabled', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        Event::listen('RecoveryCodesGenerated', function ($event) {
            $this->baddybugs->record('auth', '2fa_recovery_generated', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }

    protected function trackImpersonation(): void
    {
        if (!config('baddybugs.collectors.auth.options.track_impersonation', true)) {
            return;
        }

        // Common impersonation event (e.g., from laravel-impersonate package)
        Event::listen('Impersonation*', function ($eventName, $payload) {
            $this->baddybugs->record('auth', 'impersonation', [
                'event' => $eventName,
                'admin_id' => $payload['admin']->getAuthIdentifier() ?? null,
                'target_id' => $payload['target']->getAuthIdentifier() ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String(),
                'severity' => 'high',
            ]);
        });
    }

    /**
     * Sanitize credentials to never log passwords
     */
    protected function sanitizeCredentials(array $credentials): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'secret'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($credentials[$key])) {
                $credentials[$key] = '[REDACTED]';
            }
        }

        return $credentials;
    }
}
