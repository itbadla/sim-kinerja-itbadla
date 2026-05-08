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

Route::get('/stop-impersonate', function () {
    if (session()->has('impersonated_by')) {
        $adminId = session()->get('impersonated_by');
        session()->forget('impersonated_by');
        auth()->loginUsingId($adminId);
    }
    return redirect()->route('admin.users.index');
})->name('stop.impersonate');


/*
|--------------------------------------------------------------------------
| Authenticated Routes (SPA Mode dengan wire:navigate)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    
    // ==========================================
    // UTAMA
    // ==========================================
    Volt::route('/dashboard', 'pages.dashboard')
        ->name('dashboard')
        ->middleware('can:dasbor');

    Volt::route('/profile', 'pages.profile')
        ->name('profile')
        ->middleware('can:profil-saya');


    // ==========================================
    // MODUL PERENCANAAN (RAKER)
    // ==========================================
    Route::prefix('perencanaan')->name('perencanaan.')->group(function () {
        
        // Input & Kelola Program Kerja
        Volt::route('/proker', 'pages.perencanaan.proker.index')
            ->name('proker.index')
            ->middleware('can:program-kerja');

        // Verifikasi Proker oleh Pimpinan / LPM
        Volt::route('/verifikasi', 'pages.perencanaan.verifikasi.index')
            ->name('verifikasi.index')
            ->middleware('can:verifikasi-raker');
    });


    // ==========================================
    // MODUL KINERJA (LOGBOOK)
    // ==========================================
    Route::prefix('kinerja')->name('kinerja.')->group(function () {
        
        // Akses logbook harian staf/dosen
        Volt::route('/logbook', 'pages.logbook.index')
            ->name('logbook.index')
            ->middleware('can:logbook-harian');

        // Team Saya (Dashboard Pantauan Atasan)
        Volt::route('/team', 'pages.kinerja.team.index')
            ->name('team.index')
            ->middleware('can:team-saya');
    });

    Route::prefix('verifikasi')->name('verifikasi.')->group(function () {
        
        // Verifikasi logbook oleh atasan langsung
        Volt::route('/logbook', 'pages.verifikasi.logbook')
            ->name('logbook.index')
            ->middleware('can:verifikasi-logbook');
    });


    // ==========================================
    // MODUL KEUANGAN
    // ==========================================
    Route::prefix('keuangan')->name('keuangan.')->group(function () {
        
        // Pengajuan Dana oleh Unit/Staff
        Volt::route('/pengajuan', 'pages.keuangan.pengajuan.index')
            ->name('pengajuan.index')
            ->middleware('can:pengajuan-dana');
            
        // Laporan Pertanggungjawaban (LPJ)
        Volt::route('/lpj', 'pages.keuangan.lpj.index')
            ->name('lpj.index')
            ->middleware('can:laporan-lpj'); 

        // Verifikasi Keuangan & LPJ oleh Bagian Keuangan
        Volt::route('/verifikasi', 'pages.keuangan.verifikasi.index')
            ->name('verifikasi.index')
            ->middleware('can:verifikasi-keuangan');
    });


    // ==========================================
    // ADMINISTRATOR AREA (RBAC & MASTER DATA)
    // ==========================================
    Route::prefix('admin')->name('admin.')->group(function () {
        
        // Kelola User
        Volt::route('/users', 'pages.users.index')
            ->name('users.index')
            ->middleware('can:kelola-user');
        
        // Kelola Unit & Hierarki
        Route::middleware('can:kelola-unit')->group(function () {
            Volt::route('/units', 'pages.units.index')
                ->name('units.index');

            Volt::route('/units/{unit}', 'pages.units.detail')
                ->name('units.detail'); 
        });
            
        // Kelola Role & Permission (Spatie)
        Volt::route('/roles', 'pages.roles.index')
            ->name('roles.index')
            ->middleware('can:peran-dan-izin');

        // Kelola Master Jabatan
        Volt::route('/positions', 'pages.positions.index')
            ->name('positions.index')
            ->middleware('can:master-jabatan');

        // Kelola Master Data IKU & IKT
        Volt::route('/indikator', 'pages.indikator.index')
            ->name('indikator.index')
            ->middleware('can:indikator-kinerja');
            
    });

});


/*
|--------------------------------------------------------------------------
| Google Socialite Routes (SSO Kampus)
|--------------------------------------------------------------------------
*/

Route::get('/auth/google/redirect', function () {
    return Socialite::driver('google')->redirect();
})->name('google.login');

Route::get('/auth/google/callback', function () {
    try {
        $googleUser = Socialite::driver('google')->user();
        $email = $googleUser->getEmail();
        
        // 1. Definisikan domain institusi yang diizinkan
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

            // PEMBERIAN ROLE OTOMATIS (Sesuai dengan nama role di Seeder)
            if (\Illuminate\Support\Str::endsWith($email, '@student.ahmaddahlan.ac.id')) {
                // Pastikan role 'Magang' sudah Anda tambahkan di Seeder jika ingin menggunakan ini
                $user->assignRole('Magang'); 
            } elseif (\Illuminate\Support\Str::endsWith($email, '@staff.ahmaddahlan.ac.id')) {
                // Menggunakan huruf kapital sesuai DatabaseSeeder
                $user->assignRole('Staff'); 
            } else {
                // Domain utama (@ahmaddahlan.ac.id) diasumsikan sebagai Dosen
                $user->assignRole('Dosen');
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