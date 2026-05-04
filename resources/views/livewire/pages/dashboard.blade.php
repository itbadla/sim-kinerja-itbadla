<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use App\Models\Unit;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        // 1. Ambil data user yang sedang login beserta relasinya
        $user = Auth::user()->load(['unit.kepalaUnit', 'unit.parent.kepalaUnit', 'roles', 'assignedUnits']);
        
        // 2. Cek apakah user memimpin suatu unit (Jabatan Struktural)
        $headedUnits = Unit::where('kepala_unit_id', $user->id)->get();

        // 3. Logika Penentuan Atasan Langsung
        $atasan = null;
        if ($user->unit) {
            if ($user->unit->kepala_unit_id === $user->id) {
                // Jika dia adalah Kepala Unit, maka atasannya adalah Kepala dari Unit Induknya
                $atasan = $user->unit->parent ? $user->unit->parent->kepalaUnit : null;
            } else {
                // Jika dia staff biasa, atasannya adalah Kepala Unit tempatnya bernaung
                $atasan = $user->unit->kepalaUnit;
            }
        }

        return [
            'user' => $user,
            'headedUnits' => $headedUnits,
            'atasan' => $atasan,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header Dasbor -->
    <div>
        <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Dasbor Kinerja</h1>
        <p class="text-sm text-theme-muted mt-1">Ringkasan profil, posisi struktural, dan pencapaian Anda.</p>
    </div>

    <!-- BARIS 1: PROFIL & STRUKTUR (Grid 2 Kolom) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- KARTU 1: PROFIL PENGGUNA -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm flex flex-col sm:flex-row gap-6 items-center sm:items-start relative overflow-hidden">
            <!-- Dekorasi Latar Belakang -->
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-primary/5 rounded-full blur-2xl pointer-events-none"></div>

            <!-- Avatar Besar -->
            <div class="w-24 h-24 rounded-3xl bg-theme-body border-2 border-theme-border flex items-center justify-center text-3xl font-extrabold text-primary shadow-inner shrink-0 relative z-10">
                {{ strtoupper(substr($user->name, 0, 2)) }}
            </div>

            <!-- Detail Profil -->
            <div class="flex-1 text-center sm:text-left relative z-10">
                <h2 class="text-xl font-extrabold text-theme-text">{{ $user->name }}</h2>
                <p class="text-sm text-theme-muted mb-3">{{ $user->email }}</p>
                
                <div class="inline-block px-3 py-1.5 bg-primary/10 border border-primary/20 text-primary rounded-xl text-xs font-bold uppercase tracking-widest mb-3">
                    {{ $user->jabatan ?: 'Staff / Karyawan' }}
                </div>

                <!-- Hak Akses (Roles) -->
                <div>
                    <span class="text-[10px] text-theme-muted font-bold uppercase block mb-1">Hak Akses Sistem:</span>
                    <div class="flex flex-wrap gap-1.5 justify-center sm:justify-start">
                        @forelse($user->roles as $role)
                            <span class="px-2 py-0.5 rounded-md bg-theme-body border border-theme-border text-theme-text text-[10px] font-bold uppercase">
                                {{ $role->name }}
                            </span>
                        @empty
                            <span class="text-[10px] italic text-theme-muted">Belum ada hak akses khusus</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- KARTU 2: STRUKTUR & PENEMPATAN -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm flex flex-col justify-between relative overflow-hidden">
            <div class="relative z-10">
                <h3 class="text-xs font-bold text-theme-muted uppercase tracking-widest mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Informasi Penempatan
                </h3>

                <div class="space-y-4">
                    <!-- Homebase -->
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-theme-body border border-theme-border flex items-center justify-center text-theme-muted shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider">Unit Homebase</p>
                            <p class="text-sm font-bold text-theme-text">{{ $user->unit ? $user->unit->nama_unit : 'Belum ditempatkan' }}</p>
                        </div>
                    </div>

                    <!-- Atasan Langsung -->
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 flex items-center justify-center text-emerald-600 shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider">Atasan Langsung (Verifikator)</p>
                            @if($atasan)
                                <p class="text-sm font-bold text-theme-text">{{ $atasan->name }}</p>
                                <p class="text-[10px] text-theme-muted">{{ $atasan->email }}</p>
                            @else
                                <p class="text-sm font-bold text-emerald-600">Pimpinan Tertinggi</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Daftar Penugasan Tambahan -->
            @if($headedUnits->count() > 0 || $user->assignedUnits->count() > 0)
                <div class="mt-5 pt-4 border-t border-theme-border relative z-10">
                    @if($headedUnits->count() > 0)
                        <div class="mb-2">
                            <span class="text-[10px] text-theme-muted font-bold uppercase">Memimpin Unit:</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach($headedUnits as $unit)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-100 dark:bg-blue-500/10 dark:border-blue-500/20">
                                        {{ $unit->nama_unit }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($user->assignedUnits->count() > 0)
                        <div>
                            <span class="text-[10px] text-theme-muted font-bold uppercase">Staff Bantuan di:</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach($user->assignedUnits as $unit)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-theme-body border border-theme-border text-theme-text">
                                        {{ $unit->kode_unit ?? $unit->nama_unit }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- BARIS 2: METRIK SINGKAT (Widget) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Widget 1 -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm flex items-center justify-between group">
            <div>
                <h3 class="text-theme-muted text-xs font-bold uppercase tracking-wider mb-1">Total Logbook (Bulan Ini)</h3>
                <p class="text-3xl font-extrabold text-theme-text">0</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-theme-body border border-theme-border flex items-center justify-center text-theme-muted group-hover:text-primary group-hover:border-primary/30 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
        </div>
        
        <!-- Widget 2 -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm flex items-center justify-between group">
            <div>
                <h3 class="text-theme-muted text-xs font-bold uppercase tracking-wider mb-1">Status Kinerja</h3>
                <p class="text-xl font-extrabold text-emerald-500 mt-2 flex items-center gap-1.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Memenuhi Syarat
                </p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 flex items-center justify-center text-emerald-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
            </div>
        </div>

        <!-- Widget 3 -->
        <div class="bg-primary p-6 rounded-3xl shadow-lg shadow-primary/20 flex flex-col justify-center relative overflow-hidden text-white">
            <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
            <div class="relative z-10">
                <h3 class="text-white/80 text-xs font-bold uppercase tracking-wider mb-2">Aksi Cepat</h3>
                <a href="{{ route('kinerja.logbook.index') }}" wire:navigate class="inline-flex items-center gap-2 bg-white text-primary px-4 py-2 rounded-xl text-sm font-bold hover:bg-theme-body transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tulis Logbook
                </a>
            </div>
        </div>

    </div>
</div>