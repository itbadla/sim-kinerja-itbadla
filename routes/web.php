<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Guest Routes (Halaman Publik)
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome');


/*
|--------------------------------------------------------------------------
| Authenticated Routes (SPA Mode dengan wire:navigate)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Dashboard Utama (Akses untuk semua User: Dosen, Tendik, Admin)
    Volt::route('/dashboard', 'pages.dashboard')
        ->name('dashboard');

    // Manajemen Profil
    Volt::route('/profile', 'pages.profile')
        ->name('profile');

    /*
    |--------------------------------------------------------------------------
    | RBAC: Admin Area
    |--------------------------------------------------------------------------
    | Hanya user dengan role 'admin' yang bisa mengakses grup ini.
    | Pastikan Anda sudah menginstal Spatie Permission.
    */
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        
        // Kelola User (Dosen & Tendik)
        Volt::route('/users', 'pages.admin.users.index')
            ->name('users.index');
            
        // Kelola Role & Permission
        Volt::route('/roles', 'pages.admin.roles.index')
            ->name('roles.index');
            
    });

    /*
    |--------------------------------------------------------------------------
    | RBAC: Modul Dosen / Kinerja
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:dosen|admin'])->prefix('kinerja')->name('kinerja.')->group(function () {
        
        Volt::route('/logbook', 'pages.logbook.index')
            ->name('logbook.index');
            
        Volt::route('/tridharma', 'pages.tridharma.index')
            ->name('tridharma.index');
            
    });
});




/*
|--------------------------------------------------------------------------
| Google Socialite Routes
|--------------------------------------------------------------------------
*/

Route::get('/auth/google/redirect', function () {
    return Socialite::driver('google')->redirect();
})->name('google.login');

Route::get('/auth/google/callback', function () {
    try {
        $googleUser = Socialite::driver('google')->user();
        $email = $googleUser->getEmail();
        
        // 1. Definisikan domain yang diizinkan
        $allowedDomains = [
            '@ahmaddahlan.ac.id',
            '@staff.ahmaddahlan.ac.id',
            '@student.ahmaddahlan.ac.id'
        ];

        // 2. Tolak jika bukan email kampus
        if (!\Illuminate\Support\Str::endsWith($email, $allowedDomains)) {
            return redirect('/login')->with('error', 'Akses ditolak. Gunakan email institusi untuk masuk.');
        }

        // 3. Cari user di database
        $user = App\Models\User::where('email', $email)->first();

        if ($user) {
            // Jika user sudah ada, sinkronkan ID Google-nya
            $user->update([
                'google_id' => $googleUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            // JIKA USER BARU: Buat otomatis
            $user = App\Models\User::create([
                'name' => $googleUser->getName(),
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'password' => bcrypt(str()->random(16)), // Password acak karena login pakai Google
            ]);

            // PEMBERIAN ROLE OTOMATIS BERDASARKAN DOMAIN
            if (\Illuminate\Support\Str::endsWith($email, '@student.ahmaddahlan.ac.id')) {
                // Jika mahasiswa, beri role mahasiswa (pastikan role ini ada di database)
                $user->assignRole('mahasiswa'); 
            } elseif (\Illuminate\Support\Str::endsWith($email, '@staff.ahmaddahlan.ac.id')) {
                // Jika staff, beri role tendik
                $user->assignRole('tendik');
            } else {
                // Jika domain utama (@ahmaddahlan.ac.id), beri role dosen
                $user->assignRole('dosen');
            }
        }

        // Login ke sistem
        Auth::login($user);
        return redirect()->intended('/dashboard');

    } catch (\Exception $e) {
        return redirect('/login')->with('error', 'Gagal masuk dengan Google. Pastikan Anda memilih akun yang benar.');
    }
});
require __DIR__.'/auth.php';