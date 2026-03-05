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
        $projectId = env('FIREBASE_PROJECT_ID');

        if (is_string($projectId) && trim($projectId) !== '') {
            $projectId = trim($projectId);

            $this->app['config']->set('firebase.projects.app.project_id', $projectId);

            putenv('GOOGLE_CLOUD_PROJECT='.$projectId);
            $_ENV['GOOGLE_CLOUD_PROJECT'] = $projectId;
            $_SERVER['GOOGLE_CLOUD_PROJECT'] = $projectId;

            putenv('GCLOUD_PROJECT='.$projectId);
            $_ENV['GCLOUD_PROJECT'] = $projectId;
            $_SERVER['GCLOUD_PROJECT'] = $projectId;
        }

        $credentialsPath = env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS'));
        $credentialsJson = env('FIREBASE_CREDENTIALS_JSON');
        $credentialsBase64 = env('FIREBASE_CREDENTIALS_BASE64');

        if (is_string($credentialsBase64) && trim($credentialsBase64) !== '') {
            $decoded = base64_decode($credentialsBase64, true);

            if (is_string($decoded) && trim($decoded) !== '') {
                $credentialsJson = $decoded;
            }
        }

        if (is_string($credentialsPath) && trim($credentialsPath) !== '') {
            $credentialsPath = trim($credentialsPath);

            if (str_starts_with($credentialsPath, '{')) {
                $this->app['config']->set('firebase.projects.app.credentials', $credentialsPath);

                return;
            }

            $normalized = str_replace('\\', '/', $credentialsPath);

            if (str_starts_with($normalized, '/storage/')) {
                $normalized = base_path(ltrim($normalized, '/'));
            } elseif (! str_starts_with($normalized, '/') && ! preg_match('/^[A-Za-z]:\//', $normalized)) {
                $normalized = base_path(ltrim($normalized, '/'));
            }

            if (is_file($normalized)) {
                $this->app['config']->set('firebase.projects.app.credentials', $normalized);

                return;
            }
        }

        if (is_string($credentialsJson) && trim($credentialsJson) !== '') {
            $credentialsJson = trim($credentialsJson);
            $this->app['config']->set('firebase.projects.app.credentials', $credentialsJson);

            putenv('GOOGLE_APPLICATION_CREDENTIALS='.$credentialsJson);
            $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] = $credentialsJson;
            $_SERVER['GOOGLE_APPLICATION_CREDENTIALS'] = $credentialsJson;
        }
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
