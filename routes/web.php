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
    return redirect()->route('admin.users.index'); // Sesuaikan dengan route kelola user Anda
})->name('stop.impersonate');


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
    | Menggunakan "can:" (permission) agar lebih fleksibel dibanding "role:".
    | Jika ada staff non-admin yang diberi tugas kelola unit, ia tetap bisa masuk.
    */
    Route::prefix('admin')->name('admin.')->group(function () {
        
        // Kelola User
        Volt::route('/users', 'pages.admin.users.index')
            ->name('users.index')
            ->middleware('can:kelola-user');
        
        // Kelola Unit
        Route::middleware('can:kelola-unit')->group(function () {
            // Daftar Unit
            Volt::route('/units', 'pages.admin.units.index')
                ->name('units.index');

            // Detail & Pengaturan Unit (Menggunakan Route Model Binding {unit})
            Volt::route('/units/{unit}', 'pages.admin.units.detail')
                ->name('units.detail'); 
        });
            
        // Kelola Role & Permission
        Volt::route('/roles', 'pages.admin.roles.index')
            ->name('roles.index')
            ->middleware('can:kelola-role');
            
    });

    /*
    |--------------------------------------------------------------------------
    | RBAC: Area Verifikator (Untuk Atasan / Kepala Unit)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['can:be-atasan'])->prefix('verifikasi')->name('verifikasi.')->group(function () {
        // Halaman tempat Kepala Unit melihat/approve logbook bawahannya
        Volt::route('/logbook', 'pages.verifikasi.logbook')
            ->name('logbook.index');
            
    });

    /*
    |--------------------------------------------------------------------------
    | RBAC: Modul Kinerja (Dosen, Staff, Magang)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['can:isi-logbook'])->prefix('kinerja')->name('kinerja.')->group(function () {
        
        // Akses logbook umum (Tendik, Magang, Dosen)
        Volt::route('/logbook', 'pages.logbook.index')
            ->name('logbook.index');
            
        // Akses khusus dosen untuk input Tridharma
        Volt::route('/tridharma', 'pages.tridharma.index')
            ->name('tridharma.index')
            ->middleware('can:akses-tridharma');
            
    });

    /*
    |--------------------------------------------------------------------------
    | RBAC: Modul Keuangan (Pengajuan Dana)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth', 'verified', 'can:ajukan-dana'])->prefix('keuangan')->name('keuangan.')->group(function () {
          // status anggaran
        Volt::route('/status-anggaran', 'pages.keuangan.status-anggaran.index')
            ->name('status-anggaran.index');
        // Halaman Riwayat & Form Pengajuan Dana
        Volt::route('/pengajuan', 'pages.keuangan.pengajuan.index')
            ->name('pengajuan.index');
        // Halaman Laporan LPJ
        Volt::route('/lpj', 'pages.keuangan.lpj.index')
            ->name('lpj.index'); // Gunakan permission yang sama dengan pengajuan
        // verifikasi 
        Volt::route('/verifikasi', 'pages.keuangan.verifikasi.index')
            ->name('verifikasi.index')
            ->middleware('can:verifikasi-dana');
        // Halaman Verifikasi LPJ (Hanya untuk Admin Keuangan)
        Volt::route('/verifikasi-lpj', 'pages.keuangan.verifikasi-lpj.index')
            ->name('verifikasi-lpj.index')
            ->middleware('can:verifikasi-dana');
        // pengembalian dana
        Volt::route('/pengembalian-dana', 'pages.keuangan.pengembalian.index')
            ->name('pengembalian.index')
            ->middleware('can:verifikasi-dana');
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

            // PEMBERIAN ROLE OTOMATIS (Sesuai dengan nama role di Seeder kita)
            if (\Illuminate\Support\Str::endsWith($email, '@student.ahmaddahlan.ac.id')) {
                // Role untuk mahasiswa magang
                $user->assignRole('magang'); 
            } elseif (\Illuminate\Support\Str::endsWith($email, '@staff.ahmaddahlan.ac.id')) {
                // Role untuk staf/lembaga
                $user->assignRole('lembaga'); 
            } else {
                // Domain utama (@ahmaddahlan.ac.id) untuk dosen
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