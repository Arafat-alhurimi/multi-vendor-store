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

        if (! is_file($normalized)) {
            $credentialsJson = env('FIREBASE_CREDENTIALS_JSON');
            $credentialsBase64 = env('FIREBASE_CREDENTIALS_BASE64');

            if (is_string($credentialsBase64) && trim($credentialsBase64) !== '') {
                $decoded = base64_decode($credentialsBase64, true);

                if (is_string($decoded) && trim($decoded) !== '') {
                    $credentialsJson = $decoded;
                }
            }

            if (is_string($credentialsJson) && trim($credentialsJson) !== '') {
                $runtimePath = storage_path('app/firebase/service-account.runtime.json');
                $runtimeDir = dirname($runtimePath);

                if (! is_dir($runtimeDir)) {
                    mkdir($runtimeDir, 0775, true);
                }

                file_put_contents($runtimePath, $credentialsJson);
                $normalized = $runtimePath;
            }
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
