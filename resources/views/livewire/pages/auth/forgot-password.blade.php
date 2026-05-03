<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div class="w-full">
    <!-- Header Form Forgot Password -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Lupa Password?</h2>
    </div>

    <!-- Deskripsi Instruksi -->
    <div class="mb-6 text-sm text-slate-600 leading-relaxed text-center bg-slate-50 p-4 rounded-xl border border-slate-100">
        Tidak masalah. Masukkan alamat email Anda yang terdaftar pada sistem, dan kami akan mengirimkan tautan untuk mengatur ulang password Anda.
    </div>

    <!-- Session Status (Pesan Sukses) -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-6">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700">Alamat Email</label>
            <div class="mt-2 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                </div>
                <input wire:model="email" id="email" type="email" class="block w-full pl-10 py-3 text-slate-900 border-slate-200 rounded-xl focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm transition-colors shadow-sm" required autofocus placeholder="email@ahmaddahlan.ac.id">
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-500" />
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
            <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-md shadow-emerald-100 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all hover:-translate-y-0.5">
                Kirim Tautan Reset Password
            </button>
        </div>
    </form>

    <!-- Back to Login Link -->
    <div class="mt-8 text-center">
        <a href="{{ route('login') }}" class="inline-flex items-center text-sm font-medium text-slate-400 hover:text-slate-700 transition-colors" wire:navigate>
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke halaman Log In
        </a>
    </div>
</div>