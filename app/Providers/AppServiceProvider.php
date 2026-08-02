<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\Channels\SmsChannel;
use App\Services\SettingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
            } catch (\Throwable $e) {
                $view->with('platformSettings', []);
            }
        });

        // Dynamically set app name from settings
        try {
            Config::set('app.name', SettingService::get('site_name', 'UniAcademic'));
        } catch (\Throwable $e) {
            Config::set('app.name', 'UniAcademic');
        }
    }
}
