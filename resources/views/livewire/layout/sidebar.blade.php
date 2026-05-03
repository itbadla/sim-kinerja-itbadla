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
        
        @if(auth()->user()->can('akses-dashboard'))
        <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dasbor
        </x-nav-link-sidebar>
        @endif

        @if(auth()->user()->can('akses-profil'))
        <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Profil Saya
        </x-nav-link-sidebar>
        @endif


        <!-- ================= GRUP: KINERJA ================= -->
        @if(auth()->user()->can('isi-logbook') || auth()->user()->can('akses-tridharma') || auth()->user()->can('verifikasi-logbook'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Modul Kinerja</p>
            
            @if(auth()->user()->can('isi-logbook'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="route('kinerja.logbook.index')" :active="request()->routeIs('kinerja.logbook.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Logbook Harian
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('akses-tridharma'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="route('kinerja.tridharma.index')" :active="request()->routeIs('kinerja.tridharma.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                Tri Dharma
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('verifikasi-logbook'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Verifikasi Kinerja
            </x-nav-link-sidebar>
            @endif
        @endif


        <!-- ================= GRUP: KEUANGAN ================= -->
        @if(auth()->user()->can('ajukan-dana') || auth()->user()->can('track-dana') || auth()->user()->can('verifikasi-dana') || auth()->user()->can('kelola-lpj'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Modul Keuangan</p>
            
            @if(auth()->user()->can('ajukan-dana'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Pengajuan Dana
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('track-dana'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Status Anggaran
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('verifikasi-dana'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Verifikasi Keuangan
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('kelola-lpj'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                Laporan LPJ
            </x-nav-link-sidebar>
            @endif
        @endif


        <!-- ================= GRUP: LEMBAGA ================= -->
        @if(auth()->user()->can('monitoring-unit') || auth()->user()->can('akses-lppm') || auth()->user()->can('akses-lpm'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Lembaga & Monitoring</p>
            
            @if(auth()->user()->can('monitoring-unit'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Monitoring Unit
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('akses-lppm'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                Validasi LPPM
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('akses-lpm'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Audit Mutu (LPM)
            </x-nav-link-sidebar>
            @endif
        @endif


        <!-- ================= GRUP: DOKUMEN ================= -->
        @if(auth()->user()->can('akses-dokumen'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Dokumen</p>
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Pusat Unduhan
            </x-nav-link-sidebar>
        @endif


        <!-- ================= GRUP: RBAC (ADMIN) ================= -->
        @if(auth()->check() && auth()->user()->hasRole('admin'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Administrator</p>
            
            @if(auth()->user()->can('kelola-user'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Kelola User
            </x-nav-link-sidebar>
            @endif
            
            @if(auth()->user()->can('kelola-role'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Peran & Izin
            </x-nav-link-sidebar>
            @endif

            @if(auth()->user()->can('kelola-master'))
            <x-nav-link-sidebar class="{{ request()->routeIs('dashboard') ? 'sidebar-active' : '' }}" :href="'#'" :active="false" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                Master Data
            </x-nav-link-sidebar>
            @endif
        @endif
    </nav>
    <!-- @endpersist -->
</aside>