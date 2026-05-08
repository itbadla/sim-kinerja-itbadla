<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Logbook;
use App\Models\Unit;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterStatus = 'pending'; // Default: Hanya tampilkan yang butuh diverifikasi

    // State untuk Modal Penolakan (Revisi)
    public $isRejectModalOpen = false;
    public $selectedLogbookId = null;
    public $catatan = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    // ==========================================
    // FUNGSI: SETUJUI (APPROVE)
    // ==========================================
    public function approve($id)
    {
        $logbook = Logbook::findOrFail($id);
        
        // Pastikan atasan berhak memverifikasi ini (opsional layer keamanan ganda)
        $managedUnitIds = Unit::where('kepala_unit_id', auth()->id())->pluck('id')->toArray();
        if (!in_array($logbook->unit_id, $managedUnitIds)) {
            return; // Bukan bawahan dia
        }

        $logbook->update([
            'status' => 'approved',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'catatan_verifikator' => null // Hapus catatan jika sebelumnya pernah ditolak
        ]);
    }

    // ==========================================
    // FUNGSI: TOLAK / REVISI (REJECT)
    // ==========================================
    public function openRejectModal($id)
    {
        $this->selectedLogbookId = $id;
        $this->catatan = '';
        $this->isRejectModalOpen = true;
    }

    public function reject()
    {
        $this->validate([
            'catatan' => 'required|string|min:5'
        ], [
            'catatan.required' => 'Wajib memberikan alasan mengapa logbook ini ditolak.',
            'catatan.min' => 'Catatan terlalu singkat.'
        ]);

        $logbook = Logbook::findOrFail($this->selectedLogbookId);
        
        $logbook->update([
            'status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'catatan_verifikator' => $this->catatan
        ]);

        $this->isRejectModalOpen = false;
        $this->selectedLogbookId = null;
    }

    // ==========================================
    // FUNGSI: MENAMPILKAN DATA (READ)
    // ==========================================
    public function with(): array
    {
        // 1. Cek apakah user adalah Admin
        $isAdmin = auth()->user()->hasRole('Super Admin');

        // 2. Tentukan Unit mana saja yang bisa diakses
        if ($isAdmin) {
            // Jika admin, ambil semua ID unit untuk keperluan query logbook
            $managedUnitIds = Unit::pluck('id');
            $managedUnitNames = ['Semua Unit (Mode Admin)'];
        } else {
            // Jika bukan admin, hanya ambil unit yang dipimpin langsung
            $managedUnitIds = Unit::where('kepala_unit_id', auth()->id())->pluck('id');
            $managedUnitNames = Unit::whereIn('id', $managedUnitIds)->pluck('kode_unit')->toArray();
        }

        // 3. Ambil data logbook dengan filter
        $logbooks = Logbook::with(['user', 'unit'])
            // Jika bukan admin, batasi berdasarkan unit yang dipimpin. 
            // Jika admin, query ini dilewati (melihat semua unit).
            ->when(!$isAdmin, function($query) use ($managedUnitIds) {
                $query->whereIn('unit_id', $managedUnitIds);
            })
            ->where('status', '!=', 'draft') // Draft tetap disembunyikan dari siapapun
            ->when($this->filterStatus !== 'semua', function($query) {
                $query->where('status', $this->filterStatus);
            })
            ->where(function($query) {
                $query->whereHas('user', function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })->orWhere('deskripsi_aktivitas', 'like', '%' . $this->search . '%');
            })
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->paginate(15);

        return [
            'logbooks' => $logbooks,
            'managedUnits' => $managedUnitNames
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Verifikasi Kinerja</h1>
            <p class="text-sm text-theme-muted mt-1">
                Mengelola logbook staf untuk unit: 
                <span class="font-bold text-primary">{{ empty($managedUnits) ? 'Tidak ada unit' : implode(', ', $managedUnits) }}</span>
            </p>
        </div>
    </div>

    <!-- Filter & Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex flex-col sm:flex-row items-center gap-3">
        <!-- Pencarian -->
        <div class="relative w-full sm:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input 
                type="text" 
                wire:model.live="search" 
                autocomplete="off" 
                class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all" 
                placeholder="Cari nama staf atau aktivitas..."
            >
        </div>

        <!-- Filter Status -->
        <div class="w-full sm:w-auto shrink-0">
            <select wire:model.live="filterStatus" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-4 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium cursor-pointer">
                <option value="pending">⏳ Menunggu Verifikasi</option>
                <option value="approved">✅ Sudah Disetujui</option>
                <option value="rejected">❌ Ditolak / Revisi</option>
                <option value="semua">📂 Tampilkan Semua</option>
            </select>
        </div>
    </div>

    <!-- Tabel Data (READ) -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
    <thead class="bg-theme-body/50 border-b border-theme-border">
        <tr>
            <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest whitespace-nowrap">Staf & Waktu</th>
            
            <!-- TAMBAHAN: Kolom Unit khusus untuk Admin -->
            @if(auth()->user()->hasRole('Super Admin'))
                <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Unit Kerja</th>
            @endif

            <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-1/2">Aktivitas</th>
            <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center">Status</th>
            <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Tindakan</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-theme-border">
        @forelse($logbooks as $logbook)
            <tr wire:key="verify-{{ $logbook->id }}" class="hover:bg-theme-body/30 transition-colors">
                
                <!-- Kolom Staf & Waktu -->
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                            {{ substr($logbook->user->name, 0, 2) }}
                        </div>
                        <div>
                            <div class="text-sm font-bold text-theme-text">{{ $logbook->user->name }}</div>
                            <!-- Jika bukan admin, kode unit tetap ditampilkan kecil di sini sebagai context -->
                            @if(!auth()->user()->hasRole('Super Admin'))
                                <div class="text-[10px] uppercase font-bold text-theme-muted tracking-wider">{{ $logbook->unit->kode_unit ?? 'Unit' }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="text-xs text-theme-muted flex items-center gap-1.5 mt-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        {{ $logbook->tanggal->format('d M Y') }}
                    </div>
                </td>

                <!-- TAMBAHAN: Data Unit khusus untuk Admin -->
                @if(auth()->user()->hasRole('Super Admin'))
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-bold text-theme-text">{{ $logbook->unit->nama_unit ?? 'N/A' }}</div>
                        <div class="text-[10px] font-mono text-primary font-bold uppercase tracking-widest">{{ $logbook->unit->kode_unit ?? '-' }}</div>
                    </td>
                @endif

                <!-- Kolom Aktivitas -->
                <td class="px-6 py-4">
                    <p class="text-sm text-theme-text font-medium">{{ $logbook->deskripsi_aktivitas }}</p>
                    @if($logbook->output)
                        <p class="text-xs text-theme-muted mt-1.5 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="font-semibold text-theme-text/80">Output:</span> {{ $logbook->output }}
                        </p>
                    @endif
                    
                    @if($logbook->link_bukti)
                        <a href="{{ $logbook->link_bukti }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded mt-2 hover:underline">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                            Lihat Bukti
                        </a>
                    @endif
                </td>

                <!-- Kolom Status -->
                <td class="px-6 py-4 text-center align-middle">
                    @if($logbook->status === 'pending')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-500">MENUNGGU</span>
                    @elseif($logbook->status === 'approved')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-500">DISETUJUI</span>
                    @elseif($logbook->status === 'rejected')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-500">DITOLAK</span>
                    @endif
                </td>

                <!-- Kolom Aksi -->
                <td class="px-6 py-4 text-right whitespace-nowrap">
                    <div class="flex items-center justify-end gap-2">
                        <button wire:click="approve({{ $logbook->id }})" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-green-500 hover:bg-green-600 rounded-lg shadow-sm transition-colors {{ $logbook->status === 'approved' ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $logbook->status === 'approved' ? 'disabled' : '' }}>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Setuju
                        </button>

                        <button wire:click="openRejectModal({{ $logbook->id }})" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-theme-text bg-theme-body border border-theme-border hover:bg-red-50 hover:text-red-600 hover:border-red-200 dark:hover:bg-red-500/10 dark:hover:border-red-500/30 rounded-lg shadow-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Tolak
                        </button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ auth()->user()->hasRole('Super Admin') ? '5' : '4' }}" class="px-6 py-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-theme-body mb-4">
                        <svg class="w-8 h-8 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-base font-bold text-theme-text">Tidak Ada Logbook</h3>
                    <p class="text-sm text-theme-muted mt-1">Belum ada aktivitas yang perlu diverifikasi saat ini.</p>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
        </div>
        
        @if($logbooks->hasPages())
            <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">
                {{ $logbooks->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- MODAL PENOLAKAN (CATATAN REVISI) -->
    <!-- ========================================== -->
    @if($isRejectModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-md overflow-hidden p-6 text-left">
                <h3 class="text-xl font-bold text-theme-text mb-2">Tolak & Beri Catatan</h3>
                <p class="text-sm text-theme-muted mb-4">Beritahu staf apa yang perlu diperbaiki dari laporan kinerjanya.</p>
                
                <form wire:submit.prevent="reject">
                    <div class="mb-5">
                        <textarea wire:model="catatan" rows="4" class="block w-full border border-theme-border bg-theme-body rounded-xl py-3 px-4 text-sm focus:ring-red-500 focus:border-red-500 text-theme-text resize-none" placeholder="Contoh: Lampirkan link google drive untuk laporannya..."></textarea>
                        @error('catatan') <span class="text-xs text-red-500 font-medium mt-2 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="$set('isRejectModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-theme-muted hover:text-theme-text bg-theme-body rounded-xl border border-theme-border transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-md transition-all">
                            Konfirmasi Penolakan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>