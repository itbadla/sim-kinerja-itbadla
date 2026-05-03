<?php
use Livewire\Volt\Component;

new class extends Component {
    // Logika tambahan untuk sidebar bisa Anda masukkan ke sini nantinya
    // Misalnya menghitung jumlah notifikasi logbook yang belum dibaca
}; ?>

<aside 
    class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-theme-surface border-r border-theme-border transition-transform duration-300 lg:static lg:translate-x-0"
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

    <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
        <!-- ================= GRUP: UTAMA ================= -->
        <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mb-4 mt-2">Utama</p>
        
        <x-nav-link-sidebar :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dasbor
        </x-nav-link-sidebar>


        <!-- ================= GRUP: KINERJA ================= -->
        <!-- Logika Native: Muncul jika punya salah satu izin -->
        @if(auth()->user()->can('isi-logbook') || auth()->user()->can('akses-tridharma'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Modul Kinerja</p>
        @endif
        
        <!-- Menu Logbook -->
        @if(auth()->user()->can('isi-logbook'))
            <x-nav-link-sidebar :href="route('kinerja.logbook.index')" :active="request()->routeIs('kinerja.logbook.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Logbook Harian
            </x-nav-link-sidebar>
        @endif

        <!-- Menu Tri Dharma -->
        @if(auth()->user()->can('akses-tridharma'))
            <x-nav-link-sidebar :href="route('kinerja.tridharma.index')" :active="request()->routeIs('kinerja.tridharma.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                Tri Dharma
            </x-nav-link-sidebar>
        @endif


        <!-- ================= GRUP: RBAC (ADMIN) ================= -->
        @if(auth()->check() && auth()->user()->hasRole('admin'))
            <p class="px-3 text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mt-8 mb-4">Administrator</p>
            
            <x-nav-link-sidebar :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Kelola User
            </x-nav-link-sidebar>
            
            <x-nav-link-sidebar :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')" wire:navigate>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Peran & Izin
            </x-nav-link-sidebar>
        @endif

    </nav>
</aside>