<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

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

        // 1. Daftar domain yang diizinkan (sesuai kebutuhan Anda)
        $allowedDomains = [
            'ahmaddahlana.ac.id', 
            'staff.ahmaddahlan.ac.id', 
            'student.ahmaddahlan.ac.id'
        ];

        // 2. Ambil domain dari email user
        $userDomain = substr(strrchr($email, "@"), 1);

        if (!in_array($userDomain, $allowedDomains)) {
            return redirect('/login')->with('error', 'Gunakan email resmi @ahmaddahlan.ac.id');
        }

        // 3. Logika Cari atau Buat User (Tanpa DB::raw)
        $user = User::where('email', $email)->first();

        if ($user) {
            // Jika user sudah ada, cukup update data profil & google_id
            $user->update([
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
            ]);
        } else {
            // Jika user baru, buat akun dengan password random
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'password' => bcrypt(str()->random(16)),
            ]);
        }

        // 4. Login
        Auth::login($user);
        
        return redirect()->intended(route('dashboard', absolute: false));

    } catch (\Exception $e) {
        // Tips: gunakan $e->getMessage() untuk debug jika masih error
        return redirect('/login')->with('error', 'Gagal login via Google.');
    }
});

/*
|--------------------------------------------------------------------------
| Standard Breeze Routes
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';