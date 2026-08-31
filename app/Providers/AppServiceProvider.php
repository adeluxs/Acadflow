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
        $this->app->bind(MalwareScannerInterface::class, function () {
            return config('media.scanner') === 'clamav' ? new ClamAvMalwareScanner() : new NullMalwareScanner();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

        RateLimiter::for('ai', function (Request $request): Limit {
            $universityId = $request->user()?->university_id;
            $perMinute = app(AiRuntimeConfigService::class)->rateLimitPerMinute($universityId);

            return Limit::perMinute($perMinute)
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('challenge-votes', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('secure-downloads', function (Request $request): Limit {
            return Limit::perMinute(30)
                ->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('commerce-webhooks', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });


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

        // Share platform settings with all views
        View::composer('*', function ($view) {
            try {
                $view->with('platformSettings', SettingService::getPlatformSettings());
                $view->with('featureStates', FeatureAccessService::clientSnapshot(auth()->user()));
            } catch (\Throwable $e) {
                $view->with('platformSettings', []);
                $view->with('featureStates', []);
            }
        });

        // Dynamically set app name from settings
        try {
            Config::set('app.name', SettingService::get('site_name', 'AcadFlow'));
        } catch (\Throwable $e) {
            Config::set('app.name', 'AcadFlow');
        }
    }
}
