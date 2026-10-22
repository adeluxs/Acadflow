<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\Channels\SmsChannel;
use App\Models\ContentDocument;
use App\Models\CourseMaterial;
use App\Models\KnowledgePublication;
use App\Models\ResearchProject;
use App\Observers\SearchableContentObserver;
use App\Services\SettingService;
use App\Services\Ai\AiRuntimeConfigService;
use App\Services\FeatureAccessService;
use App\Contracts\Media\MalwareScannerInterface;
use App\Support\Database\MysqlIdentifierGuard;
use App\Services\Media\ClamAvMalwareScanner;
use App\Services\Media\NullMalwareScanner;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Minishlink\WebPush\WebPush::class, function () {
            return new \Minishlink\WebPush\WebPush(['VAPID' => app(\App\Services\WebPushConfiguration::class)->auth()], ['TTL' => 86400], (int) config('services.webpush.timeout', 8), ['connect_timeout' => (int) config('services.webpush.connect_timeout', 3), 'allow_redirects' => false]);
        });
        $this->app->bind(MalwareScannerInterface::class, function () {
            return config('media.scanner') === 'clamav' ? new ClamAvMalwareScanner() : new NullMalwareScanner();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Request-local static caches are intentionally fast, but long-lived queue
        // workers must not carry them across jobs after settings/feature changes.
        $flushLocalRuntimeCaches = static function (): void {
            SettingService::flushRequestCache();
            FeatureAccessService::flushRequestCache();
        };
        $this->app->terminating($flushLocalRuntimeCaches);
        Event::listen(JobProcessed::class, static fn () => $flushLocalRuntimeCaches());
        Event::listen(JobExceptionOccurred::class, static fn () => $flushLocalRuntimeCaches());

        if ($this->app->runningInConsole()) {
            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                $command = (string) ($event->command ?? '');

                if ($command === 'migrate' || str_starts_with($command, 'migrate:')) {
                    (new MysqlIdentifierGuard())->assertValid(database_path('migrations'));
                }
            });
        }

        ContentDocument::observe(SearchableContentObserver::class);
        CourseMaterial::observe(SearchableContentObserver::class);
        KnowledgePublication::observe(SearchableContentObserver::class);
        ResearchProject::observe(SearchableContentObserver::class);
        foreach ([\App\Models\ContentComment::class, \App\Models\SubmissionGrade::class, \App\Models\ResearchCorrection::class, \App\Models\ResearchProject::class,
            \App\Models\ResearchValidationReport::class, \App\Models\StudyPlan::class, \App\Models\AiContentDraft::class, \App\Models\CommercialAccount::class, \App\Models\DepartmentAdoptionRequest::class,
            \App\Models\InstitutionOnboardingRequest::class, \App\Models\InstitutionAccessRequest::class] as $notifiableModel) {
            $notifiableModel::observe(\App\Observers\AcademicNotificationObserver::class);
        }

        RateLimiter::for('ai', function (Request $request): Limit {
            $universityId = $request->user()?->university_id;
            $perMinute = app(AiRuntimeConfigService::class)->rateLimitPerMinute($universityId);

            return Limit::perMinute($perMinute)
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('public-contact', fn (Request $request) => [
            Limit::perMinute(3)->by('contact-minute:'.$request->ip()),
            Limit::perHour(6)->by('contact-hour:'.$request->ip()),
        ]);
        RateLimiter::for('challenge-votes', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('secure-downloads', function (Request $request): Limit {
            return Limit::perMinute(30)
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('research-exports', fn (Request $request) => Limit::perMinute(10)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('commerce-webhooks', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });


        RateLimiter::for('commercial-account-requests', function (Request $request): Limit {
            return Limit::perHour(6)->by((string) ($request->user()?->id ?? $request->ip()));
        });
        RateLimiter::for('api', fn(Request $request) => Limit::perMinute(90)->by((string)($request->user()?->id ?? $request->ip())));

        RateLimiter::for('payments', function (Request $request): Limit {
            $limits = SettingService::getSecurityRateLimits($request->user()?->university_id);

            return Limit::perMinute($limits['payment_requests_per_minute'])
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $limits = SettingService::getSecurityRateLimits();

            return Limit::perMinute($limits['login_requests_per_minute'])
                ->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request): Limit {
            $limits = SettingService::getSecurityRateLimits();

            return Limit::perHour($limits['registration_requests_per_hour'])->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $limits = SettingService::getSecurityRateLimits();

            return Limit::perMinute($limits['password_reset_requests_per_minute'])
                ->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('verification', function (Request $request): Limit {
            $limits = SettingService::getSecurityRateLimits($request->user()?->university_id);

            return Limit::perMinute($limits['verification_requests_per_minute'])
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            $limits = SettingService::getSecurityRateLimits($request->user()?->university_id);

            return Limit::perMinute($limits['two_factor_attempts_per_minute'])
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });
        // Explicit route model binding for SubmissionTask to use uuid column
        Route::bind('task', function (string $value) {
            return \App\Models\SubmissionTask::where('uuid', $value)->firstOrFail();
        });

        // Register SMS notification channel
        Notification::extend('sms', function ($app) {
            return new SmsChannel(new \App\Services\SmsService);
        });

        // Resolve layout data once for the layout that actually consumes it.
        // A wildcard composer previously ran for every Blade partial/component,
        // repeatedly rebuilding feature snapshots and platform settings during
        // a single response even though those shared variables were unused.
        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();
            $platform = SettingService::getPlatformSettings();
            $primaryColor = (string) ($platform['primary_color'] ?? '#4f46e5');
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
                $primaryColor = '#4f46e5';
            }

            [$primaryR, $primaryG, $primaryB] = sscanf($primaryColor, '#%02x%02x%02x');
            $pwaStatus = FeatureAccessService::effectiveStatus('pwa_enabled', $user?->university_id);
            $notificationStatus = FeatureAccessService::effectiveStatus('notifications', $user?->university_id);
            $notificationsVisible = (bool) $user?->isAdmin() || $notificationStatus !== FeatureAccessService::STATUS_DISABLED;

            $view->with([
                'siteName' => (string) ($platform['site_name'] ?? 'AcadSyntra'),
                'siteFavicon' => $platform['site_favicon'] ?? null,
                'primaryColor' => $primaryColor,
                'primaryR' => (int) $primaryR,
                'primaryG' => (int) $primaryG,
                'primaryB' => (int) $primaryB,
                'pwaAvailable' => $pwaStatus === FeatureAccessService::STATUS_ENABLED || (bool) $user?->isAdmin(),
                'pwaWorkerUrl' => '/serviceworker.js'.(app()->environment('local') ? '?dev=1' : ''),
                'notificationsVisible' => $notificationsVisible,
                'unreadNotificationCount' => ($user && $notificationsVisible) ? $user->unreadNotifications()->count() : 0,
                'featurePreview' => request()->attributes->get('restricted_feature_preview'),
            ]);
        });

        View::composer(['layouts.auth', 'layouts.onboarding'], function ($view): void {
            $view->with('siteName', (string) SettingService::get('site_name', 'AcadSyntra'));
        });

        // Dynamically set app name from settings
        try {
            Config::set('app.name', SettingService::get('site_name', 'AcadSyntra'));
        } catch (\Throwable $e) {
            Config::set('app.name', 'AcadSyntra');
        }
    }
}
