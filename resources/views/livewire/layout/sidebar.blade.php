<?php

use Livewire\Volt\Component;

new class extends Component {
    /**
     * Pastikan semua fungsi di sini TIDAK memiliki parameter 
     * di dalam kurung () kecuali Anda mengirimnya via wire:click
     */
    public function logout()
    {
        auth()->guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect('/', navigate: true);
    }
}; ?>

<aside 
    x-data="{ 
        {{-- Fungsi scroll ke menu aktif --}}
        scrollToActive() {
            const activeMenu = document.querySelector('.sidebar-active');
            if (activeMenu) {
                activeMenu.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        },
        {{-- Fungsi menyimpan posisi scroll terakhir --}}
        saveScroll() {
            const container = document.getElementById('sidebar-container');
            localStorage.setItem('sidebar-scroll', container.scrollTop);
        },
        {{-- Fungsi mengembalikan posisi scroll --}}
        restoreScroll() {
            const container = document.getElementById('sidebar-container');
            const scrollPos = localStorage.getItem('sidebar-scroll');
            if (scrollPos) {
                container.scrollTop = scrollPos;
            }
        }
    }" 
    x-init="
        {{-- Jalankan restorasi scroll dan fokus menu saat halaman dimuat --}}
        setTimeout(() => {
            restoreScroll();
            scrollToActive();
        }, 100);
    "
    {{-- Update posisi setiap kali navigasi selesai --}}
    x-on:livewire:navigated.window="restoreScroll(); scrollToActive();"
    class="fixed inset-y-0 left-0 z-50 w-64 h-screen flex flex-col bg-theme-surface border-r border-theme-border transition-transform duration-300 lg:sticky lg:top-0 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
>
    <!-- Header Sidebar (Area Logo) -->
    <div class="flex items-center h-20 px-6 border-b border-theme-border shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center text-white font-extrabold shadow-sm shadow-primary/20 dark:shadow-none">
                K
            </div>
            <div class="flex flex-col">
                <span class="font-bold text-sm tracking-tight text-theme-text uppercase">SIM Kinerja</span>
                <span class="text-[10px] text-primary font-bold tracking-widest leading-none mt-0.5">ITBADLA</span>
            </div>
        </div>
        
        <!-- Tombol Close khusus untuk Mobile -->
        <button @click="sidebarOpen = false" class="lg:hidden ml-auto text-theme-muted hover:text-primary bg-theme-body p-1.5 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- @persist('sidebar-nav') -->
    <nav 
        id="sidebar-container" 
        {{-- Simpan posisi scroll setiap kali user berhenti scroll --}}
        @scroll.debounce.100ms="saveScroll()"
        class="flex-1 p-4 space-y-2 overflow-y-auto scroll-smooth custom-scrollbar"
    >
        <!-- ================= GRUP: UTAMA ================= -->
        <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mb-4 mt-2">Utama</p>
        
        @if(auth()->user()->can('dasbor'))
        <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dasbor
        </x-nav-link-sidebar>
        @endif

        @if(auth()->user()->can('profil-saya'))
        <x-nav-link-sidebar class="{{ request()->routeIs('profile') ? 'sidebar-active' : '' }}" :href="route('profile')" :active="request()->routeIs('profile')" wire:navigate>
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Profil Saya
        </x-nav-link-sidebar>
        @endif

        <!-- ================= GRUP: PERENCANAAN ================= -->
        @if(auth()->user()->can('program-kerja') || auth()->user()->can('verifikasi-raker'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Perencanaan</p>
            
            @if(auth()->user()->can('program-kerja'))
            <x-nav-link-sidebar class="{{ request()->routeIs('perencanaan.proker.*') ? 'sidebar-active' : '' }}" :href="route('perencanaan.proker.index')" :active="request()->routeIs('perencanaan.proker.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Program Kerja
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('verifikasi-raker'))
            <x-nav-link-sidebar class="{{ request()->routeIs('perencanaan.verifikasi.*') ? 'sidebar-active' : '' }}" :href="route('perencanaan.verifikasi.index')" :active="request()->routeIs('perencanaan.verifikasi.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Verifikasi Raker
            </x-nav-link-sidebar>
            @endif
        @endif

        <!-- ================= GRUP: KINERJA (LOGBOOK) ================= -->
        @if(auth()->user()->can('logbook-harian') || auth()->user()->can('verifikasi-logbook') || auth()->user()->can('team-saya'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Aktivitas Kinerja</p>
            
            @if(auth()->user()->can('logbook-harian'))
            <x-nav-link-sidebar class="{{ request()->routeIs('kinerja.logbook.*') ? 'sidebar-active' : '' }}" :href="route('kinerja.logbook.index')" :active="request()->routeIs('kinerja.logbook.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Logbook Harian
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('verifikasi-logbook'))
            <x-nav-link-sidebar class="{{ request()->routeIs('verifikasi.logbook.*') ? 'sidebar-active' : '' }}" :href="route('verifikasi.logbook.index')" :active="request()->routeIs('verifikasi.logbook.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Verifikasi Logbook
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('team-saya'))
            <x-nav-link-sidebar class="{{ request()->routeIs('kinerja.team.*') ? 'sidebar-active' : '' }}" :href="route('kinerja.team.index')" :active="request()->routeIs('kinerja.team.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Team Saya
            </x-nav-link-sidebar>
            @endif
        @endif

        <!-- ================= GRUP: KEUANGAN ================= -->
        @if(auth()->user()->can('pengajuan-dana') || auth()->user()->can('verifikasi-keuangan') || auth()->user()->can('laporan-lpj'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Modul Keuangan</p>
            
            @if(auth()->user()->can('pengajuan-dana'))
            <x-nav-link-sidebar class="{{ request()->routeIs('keuangan.pengajuan.*') ? 'sidebar-active' : '' }}" :href="route('keuangan.pengajuan.index')" :active="request()->routeIs('keuangan.pengajuan.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Pengajuan Dana
            </x-nav-link-sidebar>

            @if(auth()->user()->can('laporan-lpj'))
            <x-nav-link-sidebar class="{{ request()->routeIs('keuangan.lpj.*') ? 'sidebar-active' : '' }}" :href="route('keuangan.lpj.index')" :active="request()->routeIs('keuangan.lpj.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Laporan LPJ
            </x-nav-link-sidebar>
            @endif
            @endif

            @if(auth()->user()->can('verifikasi-keuangan'))
            <x-nav-link-sidebar class="{{ request()->routeIs('keuangan.verifikasi.*') ? 'sidebar-active' : '' }}" :href="route('keuangan.verifikasi.index')" :active="request()->routeIs('keuangan.verifikasi.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Verifikasi Keuangan
            </x-nav-link-sidebar>
            @endif
        @endif

        <!-- ================= GRUP: RBAC & MASTER (ADMIN) ================= -->
        @if(auth()->check() && auth()->user()->hasRole('Super Admin'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Master Data & Admin</p>
            
            @if(auth()->user()->can('master-jabatan'))
            <x-nav-link-sidebar class="{{ request()->routeIs('admin.positions.*') ? 'sidebar-active' : '' }}" :href="route('admin.positions.index')" :active="request()->routeIs('admin.positions.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Master Jabatan
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('kelola-user'))
            <x-nav-link-sidebar class="{{ request()->routeIs('admin.users.*') ? 'sidebar-active' : '' }}" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Kelola User
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('kelola-unit'))
            <x-nav-link-sidebar class="{{ request()->routeIs('admin.units.*') ? 'sidebar-active' : '' }}" :href="route('admin.units.index')" :active="request()->routeIs('admin.units.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Kelola Unit
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('peran-dan-izin'))
            <x-nav-link-sidebar class="{{ request()->routeIs('admin.roles.*') ? 'sidebar-active' : '' }}" :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Peran & Izin
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('indikator-kinerja'))
            <x-nav-link-sidebar class="{{ request()->routeIs('admin.indikator.*') ? 'sidebar-active' : '' }}" :href="route('admin.indikator.index')" :active="request()->routeIs('admin.indikator.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                Indikator (IKU/IKT)
            </x-nav-link-sidebar>
            @endif
        @endif
        
    </nav>
    <!-- @endpersist -->
</aside>