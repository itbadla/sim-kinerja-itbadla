<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Unit;

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
        // 2. KUNCI MASTER UNTUK ADMIN
        // Admin akan selalu mengembalikan nilai 'true' di setiap perintah @can apapun
        Gate::before(function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        Gate::define('be-atasan', function (User $user) {
        return $user->can('verifikasi-logbook');
        });
    }
    
}
