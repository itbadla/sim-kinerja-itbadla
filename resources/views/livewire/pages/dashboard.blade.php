<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

// Baris ini memberitahu Volt: "Gunakan layouts/app.blade.php sebagai template saya!"
new #[Layout('layouts.app')] class extends Component {
    // Logika dashboard di sini
}; ?>

<div>
    <h1 class="text-2xl font-extrabold text-theme-text mb-6">Dasbor Kinerja</h1>

    <!-- Contoh Kartu Bersih (Clean Card) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-theme-surface p-6 rounded-2xl border border-theme-border shadow-sm">
            <h3 class="text-theme-muted text-xs font-bold uppercase tracking-wider">Total Logbook</h3>
            <p class="text-3xl font-extrabold text-theme-text mt-2">12</p>
        </div>
        
        <div class="bg-theme-surface p-6 rounded-2xl border border-theme-border shadow-sm">
            <h3 class="text-theme-muted text-xs font-bold uppercase tracking-wider">Status BKD</h3>
            <p class="text-xl font-extrabold text-primary mt-2 flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Memenuhi Syarat
            </p>
        </div>
    </div>
</div>