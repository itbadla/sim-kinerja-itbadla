<?php
use Livewire\Volt\Component;

new class extends Component {
    // Logika Volt untuk fitur Keluar (Logout) bergaya SPA
    public function logout()
    {
        auth()->guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        // Redirect ke halaman awal tanpa refresh penuh
        return $this->redirect('/', navigate: true);
    }
}; ?>

<nav class="sticky top-0 z-40 h-20 bg-theme-surface/80 backdrop-blur-md border-b border-theme-border flex items-center px-4 md:px-8 transition-colors duration-300">
    <div class="flex items-center justify-between w-full">
        
        <!-- Sisi Kiri: Toggle Sidebar (Mobile) & Info Tambahan -->
        <div class="flex items-center">
            <!-- Tombol Hamburger: Akan mengubah state sidebarOpen di app.blade.php -->
            <button @click="sidebarOpen = true" class="p-2 mr-4 rounded-lg text-theme-muted hover:text-primary hover:bg-theme-body lg:hidden transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            
            <!-- Tanggal Hari Ini (Hanya tampil di layar besar) -->
            <div class="hidden lg:block text-sm font-medium text-theme-muted">
                {{ now()->translatedFormat('l, d F Y') }}
            </div>
        </div>

        <!-- Sisi Kanan: Theme Switcher & Profile User -->
        <div class="flex items-center gap-3">
            
            <!-- Tombol Switcher Dark Mode (Bulan/Matahari) -->
            <button 
                x-data="{ 
                    isDark: document.documentElement.classList.contains('dark'),
                    toggleTheme() { 
                        this.isDark = !this.isDark; 
                        if(this.isDark) { document.documentElement.classList.add('dark'); localStorage.theme = 'dark'; } 
                        else { document.documentElement.classList.remove('dark'); localStorage.theme = 'light'; }
                    }
                }" 
                @click="toggleTheme()"
                class="p-2 rounded-xl border border-theme-border text-theme-muted hover:text-primary hover:bg-theme-body transition-all"
                title="Ganti Tema"
            >
                <!-- Ikon Matahari -->
                <svg x-show="isDark" style="display: none;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <!-- Ikon Bulan -->
                <svg x-show="!isDark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>

            <!-- Profil Dropdown -->
            <div class="relative" x-data="{ open: false }">
                <!-- Tombol Profil -->
                <button @click="open = !open" class="flex items-center gap-3 p-1 pr-4 rounded-full border border-theme-border hover:bg-theme-body transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-theme-surface">
                    <img class="w-8 h-8 rounded-full border border-primary/20 object-cover" src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=059669&background=ECFDF5&bold=true" alt="{{ Auth::user()->name }}">
                    <span class="text-xs font-bold text-theme-text hidden sm:block">{{ Auth::user()->name }}</span>
                    <!-- Panah kecil -->
                    <svg class="w-4 h-4 text-theme-muted hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>

                <!-- Menu Dropdown -->
                <div 
                    x-show="open" 
                    @click.away="open = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 transform"
                    x-transition:enter-end="opacity-100 scale-100 transform"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100 transform"
                    x-transition:leave-end="opacity-0 scale-95 transform"
                    class="absolute right-0 mt-3 w-48 bg-theme-surface border border-theme-border rounded-2xl shadow-xl py-2 overflow-hidden" 
                    style="display: none;"
                >
                    <div class="px-4 py-2 border-b border-theme-border mb-1 sm:hidden">
                        <span class="block text-sm font-bold text-theme-text truncate">{{ Auth::user()->name }}</span>
                        <span class="block text-[10px] text-theme-muted truncate">{{ Auth::user()->email }}</span>
                    </div>

                    <!-- Link Profil dengan SPA Navigation -->
                    <a href="/profile" wire:navigate class="block px-4 py-2 text-sm font-medium text-theme-text hover:text-primary hover:bg-primary/10 transition-colors">
                        Profil Saya
                    </a>
                    
                    <hr class="border-theme-border my-1">
                    
                    <!-- Tombol Keluar dengan Logika Volt (SPA) -->
                    <button wire:click="logout" class="w-full text-left px-4 py-2 text-sm font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                        Keluar
                    </button>
                </div>
            </div>
            
        </div>
    </div>
</nav>