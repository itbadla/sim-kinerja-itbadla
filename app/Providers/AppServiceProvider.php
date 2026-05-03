<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        //
        // Jika APP_URL mengandung https, paksa skema URL menjadi https
        if (str_contains(config('app.url'), 'https')) {
            URL::forceScheme('https');
        }
    }
}
