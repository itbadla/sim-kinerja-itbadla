<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        // 1. Memuat user dengan relasi 'units' (jamak/pivot) dan role-nya
        $user = Auth::user()->load([
            'units.kepalaUnit', 
            'units.parent.kepalaUnit', 
            'roles'
        ]);

        // 2. Ambil Unit Utama (Homebase)
        $primaryUnit = $user->units->first();

        // 3. Kalkulasi Atasan Langsung (Sama seperti Dashboard)
        $atasan = null;
        if ($primaryUnit) {
            if ($primaryUnit->kepala_unit_id === $user->id) {
                // Jika dia Kepala Unit, atasannya adalah Kepala Unit Induk
                $atasan = $primaryUnit->parent ? $primaryUnit->parent->kepalaUnit : null;
            } else {
                // Jika staff biasa, atasannya adalah Kepala Unit tersebut
                $atasan = $primaryUnit->kepalaUnit;
            }
        }

        return [
            'user' => $user,
            'primaryUnit' => $primaryUnit,
            'atasan' => $atasan,
        ];
    }
}; ?>

<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Profil Saya</h1>
            <p class="text-sm text-theme-muted mt-1">Kelola informasi akun, keamanan, dan lihat penempatan struktural Anda.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- KOLOM KIRI: Informasi Struktural & Identitas (Read-Only) -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- Kartu Identitas -->
            <div class="bg-theme-surface border border-theme-border rounded-2xl p-6 shadow-sm text-center relative overflow-hidden">
                <!-- Dekorasi Background -->
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-primary/5 rounded-full blur-2xl pointer-events-none"></div>
                
                <div class="w-24 h-24 rounded-full bg-primary/10 border-4 border-white dark:border-theme-surface text-primary flex items-center justify-center font-black text-3xl mx-auto mb-4 shadow-sm uppercase z-10 relative">
                    {{ substr($user->name, 0, 2) }}
                </div>
                
                <h3 class="text-lg font-bold text-theme-text relative z-10">{{ $user->name }}</h3>
                <p class="text-sm text-theme-muted mb-4 relative z-10">{{ $user->email }}</p>
                
                <div class="flex flex-wrap gap-1.5 justify-center relative z-10">
                    @forelse($user->roles as $role)
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase border {{ $role->name === 'Super Admin' ? 'bg-theme-text text-theme-surface border-theme-text' : 'bg-theme-body border-theme-border text-theme-text' }}">
                            {{ $role->name }}
                        </span>
                    @empty
                        <span class="text-[10px] italic text-theme-muted">Belum ada hak akses khusus</span>
                    @endforelse
                </div>
            </div>

            <!-- Kartu Penempatan Struktural -->
            <div class="bg-theme-surface border border-theme-border rounded-2xl p-6 shadow-sm">
                <h4 class="text-xs font-bold text-theme-muted uppercase tracking-widest mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Informasi Penempatan
                </h4>

                <div class="space-y-5">
                    <!-- Homebase -->
                    <div>
                        <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Unit Homebase</p>
                        @if($primaryUnit)
                            <div class="flex items-center gap-2 bg-theme-body p-2.5 rounded-xl border border-theme-border">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 flex items-center justify-center shrink-0">
                                    <span class="text-xs font-bold">{{ substr($primaryUnit->kode_unit ?? 'U', 0, 2) }}</span>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-theme-text">{{ $primaryUnit->nama_unit }}</p>
                                    <p class="text-[10px] text-theme-muted uppercase">{{ $primaryUnit->kode_unit ?? '-' }}</p>
                                </div>
                            </div>
                        @else
                            <div class="text-xs italic text-theme-muted p-2.5 bg-theme-body rounded-xl border border-dashed border-theme-border text-center">
                                Belum ditempatkan di unit manapun.
                            </div>
                        @endif
                    </div>

                    <!-- Atasan Langsung -->
                    <div>
                        <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Verifikator / Atasan Langsung</p>
                        @if($atasan)
                            <div class="flex items-center gap-2 bg-theme-body p-2.5 rounded-xl border border-theme-border">
                                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                                <div class="overflow-hidden">
                                    <p class="text-xs font-bold text-theme-text truncate">{{ $atasan->name }}</p>
                                    <p class="text-[10px] text-theme-muted truncate">{{ $atasan->email }}</p>
                                </div>
                            </div>
                        @else
                            <div class="text-xs italic text-theme-muted p-2.5 bg-theme-body rounded-xl border border-dashed border-theme-border text-center flex flex-col items-center justify-center">
                                <svg class="w-4 h-4 mb-1 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                Pimpinan Tertinggi (Tidak ada atasan)
                            </div>
                        @endif
                    </div>
                    
                    <div class="pt-3 border-t border-theme-border">
                        <p class="text-[10px] text-theme-muted leading-relaxed italic text-center">
                            *Jika penempatan Unit atau Atasan Anda tidak sesuai, silakan hubungi Administrator atau Bagian Kepegawaian (SDI).
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: Form Pengaturan Akun -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Update Profile Info (Breeze Component) -->
            <div class="bg-theme-surface p-6 sm:p-8 border border-theme-border shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <!-- Update Password (Breeze Component) -->
            <div class="bg-theme-surface p-6 sm:p-8 border border-theme-border shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <!-- Delete Account (Breeze Component) -->
            <div class="bg-theme-surface p-6 sm:p-8 border border-theme-border shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>

        </div>
    </div>
</div>