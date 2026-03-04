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
        //
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
