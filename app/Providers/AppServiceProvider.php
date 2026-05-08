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
            return $user->hasRole('Super Admin') ? true : null;
        });

        Gate::define('be-atasan', function (User $user) {
        return $user->can('verifikasi-logbook');
        });

        // app/Providers/AppServiceProvider.php

        Gate::define('verifikasi-bawahan', function (User $user, User $target) {
            // 1. Cek apakah user punya hak verifikasi secara global
            if (!$user->can('verifikasi-logbook')) {
                return false;
            }

            // 2. Ambil semua Unit yang dipimpin oleh user ini
            $unitIdsDikelola = Unit::where('kepala_unit_id', $user->id)->get()->map(function($unit) {
                // Ambil ID unit itu sendiri + semua ID sub-unit di bawahnya
                return array_merge([$unit->id], $unit->getAllChildrenIds());
            })->flatten()->unique()->toArray();

            // 3. Cek apakah target berada di salah satu unit tersebut
            // Kita asumsikan target memiliki homebase di $target->units->first()
            $targetUnitId = $target->units->first()?->id;

            return in_array($targetUnitId, $unitIdsDikelola);
        });

        // Perbarui Gate be-atasan agar lebih "cerdas"
        Gate::define('be-atasan', function ($user) {
            // Izinkan jika dia punya permission verifikasi 
            // ATAU dia memegang role pimpinan (Rektor/WR/Dekan/Kaprodi)
            return $user->can('verifikasi-logbook') || 
                $user->hasAnyRole(['Rektor', 'Wakil Rektor', 'Dekan', 'Kaprodi', 'Ketua LPM', 'Ketua LPPM', 'Kepala Biro']);
        });
    }
    
}
