<?php

namespace App\Providers;

use App\Models\Promotion;
use App\Observers\PromotionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $credentials = env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS'));

        if (! is_string($credentials) || trim($credentials) === '') {
            return;
        }

        $credentials = trim($credentials);

        if (str_starts_with($credentials, '{')) {
            return;
        }

        $normalized = str_replace('\\', '/', $credentials);

        if (str_starts_with($normalized, '/storage/')) {
            $normalized = base_path(ltrim($normalized, '/'));
        } elseif (! str_starts_with($normalized, '/') && ! preg_match('/^[A-Za-z]:\//', $normalized)) {
            $normalized = base_path(ltrim($normalized, '/'));
        }

        $this->app['config']->set('firebase.projects.app.credentials', $normalized);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (PHP_SAPI !== 'cli') {
            @ini_set('max_execution_time', '300');

            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
        }

        Promotion::observe(PromotionObserver::class);
    }
}
