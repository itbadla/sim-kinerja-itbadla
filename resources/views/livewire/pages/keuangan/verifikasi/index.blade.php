<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\FundSubmission;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterStatus = 'pending'; // Default melihat yang pending

    // ==========================================
    // STATE: MODAL VERIFIKASI
    // ==========================================
    public $isModalOpen = false;
    public ?FundSubmission $selectedSubmission = null;
    
    // Field Input Verifikasi
    public $actionStatus = ''; // 'approved' atau 'rejected'
    public $catatan = '';

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }

    // ==========================================
    // FUNGSI: BUKA MODAL
    // ==========================================
    public function openModal($id)
    {
        $this->resetValidation();
        $this->selectedSubmission = FundSubmission::with(['user', 'unit'])->findOrFail($id);
        
        $this->actionStatus = '';
        $this->catatan = $this->selectedSubmission->catatan_verifikator ?? '';
        
        $this->isModalOpen = true;
    }

    // ==========================================
    // FUNGSI: SIMPAN VERIFIKASI
    // ==========================================
    public function saveVerification()
    {
        $this->validate([
            'actionStatus' => 'required|in:approved,rejected',
            // Catatan wajib diisi jika ditolak
            'catatan' => 'required_if:actionStatus,rejected|nullable|string|max:500',
        ], [
            'actionStatus.required' => 'Pilih keputusan verifikasi (Setujui/Tolak).',
            'catatan.required_if' => 'Catatan wajib diisi jika pengajuan ditolak.',
        ]);

        if ($this->selectedSubmission) {
            $this->selectedSubmission->update([
                'status' => $this->actionStatus,
                'catatan_verifikator' => $this->catatan,
            ]);

            session()->flash('success', 'Pengajuan dana berhasil diverifikasi!');
        }

        $this->isModalOpen = false;
        $this->selectedSubmission = null;
    }

    // ==========================================
    // FUNGSI: READ DATA
    // ==========================================
    public function with(): array
    {
        $user = Auth::user();
        
        // Mulai Query
        $query = FundSubmission::with(['user', 'unit'])->latest();

        // LOGIKA HAK AKSES MELIHAT DATA
        // Jika dia adalah Admin Keuangan, dia bisa melihat semua.
        // Jika dia hanya Kepala Unit, dia hanya melihat pengajuan dari unit yang dipimpinnya (dan bukan pengajuannya sendiri).
        if (!$user->hasRole(['admin', 'keuangan'])) {
            $headedUnitIds = Unit::where('kepala_unit_id', $user->id)->pluck('id');
            $query->whereIn('unit_id', $headedUnitIds)
                  ->where('user_id', '!=', $user->id); // Tidak memverifikasi pengajuan sendiri
        }

        // Filter Status
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Filter Pencarian (Cari berdasarkan nama pengaju atau keperluan)
        if ($this->search) {
            $query->where(function($q) {
                $q->where('keperluan', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', function($qu) {
                      $qu->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        return [
            'submissions' => $query->paginate(10),
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Verifikasi Keuangan</h1>
            <p class="text-sm text-theme-muted mt-1">Tinjau, setujui, atau tolak pengajuan anggaran dari bawahan Anda.</p>
        </div>
    </div>

    <!-- Alert Sukses/Error -->
    @if (session()->has('success'))
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Kotak Filter & Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex flex-col md:flex-row items-center gap-3">
        <!-- Filter Status -->
        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterStatus" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium">
                <option value="">Semua Status</option>
                <option value="pending">Menunggu Verifikasi (Pending)</option>
                <option value="approved">Telah Disetujui</option>
                <option value="rejected">Telah Ditolak</option>
            </select>
        </div>

        <!-- Pencarian -->
        <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama pengaju atau rincian keperluan..." class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all">
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-48">Pengaju & Tanggal</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Detail Keperluan</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Nominal (Rp)</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center w-32">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($submissions as $item)
                        <tr class="hover:bg-theme-body/30 transition-colors group" wire:key="verify-{{ $item->id }}">
                            
                            <!-- Pengaju & Tanggal -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm font-bold text-theme-text">{{ $item->user->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-theme-muted mt-0.5">{{ $item->created_at->translatedFormat('d M Y, H:i') }}</div>
                            </td>
                            
                            <!-- Keperluan & Unit -->
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    @if($item->tipe_pengajuan === 'lembaga')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-900/30 dark:border-blue-800/50 dark:text-blue-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                            Lembaga: {{ $item->unit ? ($item->unit->kode_unit ?? $item->unit->nama_unit) : '-' }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-900/30 dark:border-emerald-800/50 dark:text-emerald-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            Pribadi
                                        </span>
                                    @endif
                                </div>
                                
                                <p class="text-sm font-medium text-theme-text">{{ $item->keperluan }}</p>
                            </td>

                            <!-- Nominal -->
                            <td class="px-6 py-4 align-top text-right">
                                <div class="text-sm font-extrabold text-theme-text">
                                    Rp {{ number_format($item->nominal, 0, ',', '.') }}
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 align-top text-center">
                                @if($item->status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20">Pending</span>
                                @elseif($item->status === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">Disetujui</span>
                                @elseif($item->status === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">Ditolak</span>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-6 py-4 align-top text-right">
                                <button wire:click="openModal({{ $item->id }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-theme-body hover:bg-primary hover:text-white text-theme-text text-xs font-bold rounded-lg border border-theme-border hover:border-primary transition-colors shadow-sm">
                                    Tinjau
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-theme-body mb-4 border border-theme-border shadow-inner">
                                    <svg class="w-10 h-10 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <h4 class="text-base font-bold text-theme-text uppercase tracking-tight">Tidak Ada Data</h4>
                                <p class="text-sm text-theme-muted mt-1">Belum ada pengajuan dana yang memerlukan verifikasi Anda saat ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginasi -->
        @if($submissions->hasPages())
            <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- MODAL TINJAU & VERIFIKASI -->
    <!-- ========================================== -->
    @if($isModalOpen && $selectedSubmission)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                
                <!-- Header Sticky -->
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center z-10 shrink-0">
                    <h3 class="text-lg font-bold text-theme-text">Tinjau Pengajuan Dana</h3>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="flex flex-col overflow-hidden flex-1">
                    <!-- Body Scrollable -->
                    <div class="p-6 overflow-y-auto custom-scrollbar space-y-6">
                        
                        <!-- Info Pengaju -->
                        <div class="bg-theme-body p-4 rounded-xl border border-theme-border flex items-start gap-4">
                            <div class="w-12 h-12 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-bold shadow-inner shrink-0 text-xl">
                                {{ strtoupper(substr($selectedSubmission->user->name ?? 'U', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="font-bold text-theme-text text-base">{{ $selectedSubmission->user->name ?? 'Unknown' }}</h4>
                                <p class="text-sm text-theme-muted mb-1">{{ $selectedSubmission->user->email ?? '-' }}</p>
                                
                                @if($selectedSubmission->tipe_pengajuan === 'lembaga')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-200 mt-1">
                                        Atas Nama Lembaga: {{ $selectedSubmission->unit->nama_unit ?? '-' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 mt-1">
                                        Pengajuan Pribadi (Unit: {{ $selectedSubmission->unit->nama_unit ?? '-' }})
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Detail Pengajuan -->
                        <div>
                            <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-4">Rincian Pengajuan</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                                <div>
                                    <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Tanggal Diajukan</p>
                                    <p class="text-sm font-medium text-theme-text">{{ $selectedSubmission->created_at->translatedFormat('d F Y, H:i') }} WIB</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Nominal (Rp)</p>
                                    <p class="text-lg font-extrabold text-theme-text">Rp {{ number_format($selectedSubmission->nominal, 0, ',', '.') }}</p>
                                </div>
                                <div class="col-span-1 md:col-span-2">
                                    <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Deskripsi Keperluan</p>
                                    <div class="bg-theme-body p-3 rounded-lg border border-theme-border text-sm text-theme-text leading-relaxed">
                                        {{ $selectedSubmission->keperluan }}
                                    </div>
                                </div>
                                @if($selectedSubmission->file_lampiran)
                                    <div class="col-span-1 md:col-span-2">
                                        <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Dokumen Lampiran (Proposal/RAB)</p>
                                        <a href="{{ Storage::url($selectedSubmission->file_lampiran) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-theme-body border border-theme-border rounded-xl text-sm font-bold text-theme-text hover:text-primary hover:border-primary transition-colors">
                                            <svg class="w-5 h-5 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                            Buka Dokumen Terlampir
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Form Verifikasi (Hanya jika masih pending) -->
                        @if($selectedSubmission->status === 'pending')
                            <div class="bg-primary/5 p-4 rounded-xl border border-primary/20">
                                <h4 class="text-sm font-bold text-theme-text mb-3">Keputusan Verifikasi</h4>
                                
                                <div class="grid grid-cols-2 gap-3 mb-4">
                                    <label class="cursor-pointer">
                                        <input type="radio" wire:model.live="actionStatus" value="approved" class="hidden peer">
                                        <div class="p-3 text-center rounded-xl border border-theme-border bg-theme-surface peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 transition-all shadow-sm">
                                            <svg class="w-6 h-6 mx-auto mb-1 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span class="text-sm font-bold">Setujui</span>
                                        </div>
                                    </label>
                                    
                                    <label class="cursor-pointer">
                                        <input type="radio" wire:model.live="actionStatus" value="rejected" class="hidden peer">
                                        <div class="p-3 text-center rounded-xl border border-theme-border bg-theme-surface peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600 transition-all shadow-sm">
                                            <svg class="w-6 h-6 mx-auto mb-1 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span class="text-sm font-bold">Tolak</span>
                                        </div>
                                    </label>
                                </div>
                                @error('actionStatus') <span class="text-xs text-red-500 mt-1 block mb-3 font-medium">{{ $message }}</span> @enderror

                                <div>
                                    <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Catatan Verifikator <span class="text-theme-muted font-normal normal-case">(Wajib jika ditolak)</span></label>
                                    <textarea wire:model="catatan" rows="3" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="Berikan alasan atau pesan untuk pengaju..."></textarea>
                                    @error('catatan') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @else
                            <div class="bg-theme-body p-4 rounded-xl border border-theme-border">
                                <h4 class="text-sm font-bold text-theme-text mb-2">Riwayat Keputusan</h4>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-xs text-theme-muted">Status Akhir:</span>
                                    @if($selectedSubmission->status === 'approved')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200">Disetujui</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200">Ditolak</span>
                                    @endif
                                </div>
                                @if($selectedSubmission->catatan_verifikator)
                                    <div class="bg-theme-surface p-3 rounded border border-theme-border text-sm text-theme-text italic">
                                        "{{ $selectedSubmission->catatan_verifikator }}"
                                    </div>
                                @endif
                            </div>
                        @endif

                    </div>
                    
                    <!-- Footer Sticky -->
                    <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">
                            Tutup
                        </button>
                        
                        @if($selectedSubmission->status === 'pending')
                            <button type="button" wire:click="saveVerification" wire:loading.attr="disabled" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all flex items-center gap-2 disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveVerification">Simpan Keputusan</span>
                                <span wire:loading wire:target="saveVerification">Menyimpan...</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>