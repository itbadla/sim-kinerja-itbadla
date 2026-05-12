<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\Logbook;
use App\Models\Unit;
use App\Models\Periode;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterStatus = 'pending'; 
    public $selectedPeriodeId = ''; 

    // State untuk Modal Penolakan (Revisi)
    public $isRejectModalOpen = false;
    public $selectedLogbookId = null;
    public $catatan = '';

    public function mount()
    {
        // Set dropdown ke periode yang aktif saat ini secara default
        $currentPeriode = Periode::where('is_current', true)->first();
        
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    public function updatingSelectedPeriodeId() { $this->resetPage(); }

    // ==========================================
    // FUNGSI: SETUJUI (APPROVE)
    // ==========================================
    public function approve($id)
    {
        $logbook = Logbook::findOrFail($id);
        
        $logbook->update([
            'status' => 'approved',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'catatan_verifikator' => null 
        ]);
        
        session()->flash('message', 'Logbook berhasil disetujui.');
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
            'catatan.min' => 'Catatan revisi terlalu singkat.'
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
        
        session()->flash('message', 'Logbook dikembalikan dengan catatan revisi.');
    }

    // ==========================================
    // FUNGSI: MENAMPILKAN DATA (READ - DENGAN LOGIKA BARU)
    // ==========================================
    public function with(): array
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('Super Admin');
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);

        $logbooks = collect();
        $managedUnitNames = [];

        if ($selectedPeriode) {
            // PERBAIKAN: Tambahkan relasi 'verifikator' di sini agar kita bisa memanggil namanya
            $query = Logbook::with(['user', 'unit', 'workProgram', 'verifikator'])
                ->where('periode_id', $selectedPeriode->id)
                ->where('status', '!=', 'draft');

            if ($isAdmin) {
                $managedUnitNames = ['Semua Unit (Mode Administrator)'];
            } else {
                // 1. Ambil Unit yang DIPIMPIN LANGSUNG oleh user ini
                $myManagedUnits = Unit::where('kepala_unit_id', $user->id)->get();
                $myManagedUnitIds = $myManagedUnits->pluck('id')->toArray();
                $managedUnitNames = $myManagedUnits->pluck('nama_unit')->toArray();

                // 2. Ambil Sub-Unit (Anak) dari unit-unit di atas
                $subUnits = Unit::whereIn('parent_id', $myManagedUnitIds)->get();

                // 3. LOGIKA FILTER ATASAN-BAWAHAN YANG KETAT
                $query->where(function ($q) use ($myManagedUnitIds, $subUnits, $user) {
                    
                    // KONDISI A: Staf di unit yang saya pimpin (KECUALI SAYA SENDIRI)
                    $q->where(function($qA) use ($myManagedUnitIds, $user) {
                        $qA->whereIn('unit_id', $myManagedUnitIds)
                           ->where('user_id', '!=', $user->id);
                    });

                    // KONDISI B: Jika ada sub-unit, ambil HANYA logbook milik Kepala Sub-Unit tersebut
                    if ($subUnits->count() > 0) {
                        foreach ($subUnits as $subUnit) {
                            if ($subUnit->kepala_unit_id) {
                                $q->orWhere(function($qB) use ($subUnit) {
                                    $qB->where('unit_id', $subUnit->id)
                                       ->where('user_id', $subUnit->kepala_unit_id);
                                });
                            }
                        }
                    }
                });
            }

            // Filter Status
            if ($this->filterStatus !== 'semua') {
                $query->where('status', $this->filterStatus);
            }

            // Filter Pencarian
            if ($this->search) {
                $query->where(function($q) {
                    $q->whereHas('user', function($qu) {
                        $qu->where('name', 'like', '%' . $this->search . '%');
                    })->orWhere('deskripsi_aktivitas', 'like', '%' . $this->search . '%');
                });
            }

            $logbooks = $query->orderBy('tanggal', 'desc')
                              ->orderBy('jam_mulai', 'desc')
                              ->paginate(15);
        }

        return [
            'logbooks' => $logbooks,
            'managedUnits' => $managedUnitNames,
            'allPeriodes' => $allPeriodes,
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
        <div class="flex-1">
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Verifikasi Kinerja</h1>
            <p class="text-sm text-theme-muted mt-1 leading-relaxed">
                Meninjau logbook: 
                <span class="font-bold text-primary">{{ empty($managedUnits) ? 'Tidak ada unit yang Anda pimpin' : implode(', ', $managedUnits) }}</span>
            </p>
        </div>
        
        <div class="w-full sm:w-64 shrink-0">
            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Periode Kinerja</label>
            <select wire:model.live="selectedPeriodeId" class="w-full border-gray-300 bg-gray-50 rounded-xl text-sm font-bold text-gray-900 focus:ring-primary focus:border-primary shadow-sm cursor-pointer">
                <option value="">-- Pilih Periode --</option>
                @foreach($allPeriodes as $p)
                    <option value="{{ $p->id }}">
                        {{ $p->nama_periode }} 
                        @if($p->is_current) (Aktif) @endif
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Filter & Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex flex-col sm:flex-row items-center gap-3">
        <!-- Pencarian -->
        <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live.debounce.300ms="search" autocomplete="off" class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all" placeholder="Cari nama staf atau aktivitas...">
        </div>

        <!-- Filter Status -->
        <div class="w-full sm:w-56 shrink-0">
            <select wire:model.live="filterStatus" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-4 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium cursor-pointer">
                <option value="pending">⏳ Menunggu Verifikasi</option>
                <option value="approved">✅ Sudah Disetujui</option>
                <option value="rejected">❌ Ditolak / Revisi</option>
                <option value="semua">📂 Tampilkan Semua</option>
            </select>
        </div>
    </div>

    <!-- Peringatan jika belum pilih periode -->
    @if(!$selectedPeriodeId)
        <div class="bg-amber-50 border border-amber-200 text-amber-700 rounded-xl p-4 text-sm font-medium flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            Silakan pilih Periode Kinerja terlebih dahulu untuk melihat antrean logbook.
        </div>
    @endif

    <!-- Feedback Message -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-medium shadow-sm mb-4">
            {{ session('message') }}
        </div>
    @endif

    <!-- Tabel Data (READ) -->
    @if($selectedPeriodeId)
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest whitespace-nowrap">Staf & Unit</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-1/2">Aktivitas & Output</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($logbooks as $logbook)
                        <tr wire:key="verify-{{ $logbook->id }}" class="hover:bg-theme-body/30 transition-colors align-top">
                            
                            <!-- Kolom Staf & Unit -->
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3 mb-2">
                                    <div class="w-10 h-10 rounded-full bg-primary/10 border border-primary/20 text-primary flex items-center justify-center font-bold text-sm shrink-0 uppercase">
                                        {{ substr($logbook->user->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-theme-text">{{ $logbook->user->name }}</div>
                                        <div class="text-[10px] uppercase font-bold text-blue-600 bg-blue-50 border border-blue-100 px-1.5 py-0.5 rounded mt-1 inline-block">
                                            {{ $logbook->unit->nama_unit ?? 'Unknown Unit' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-xs text-theme-muted font-medium flex items-center gap-1 mt-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    {{ $logbook->tanggal->translatedFormat('d M Y') }}
                                </div>
                                <div class="text-[11px] text-theme-muted flex items-center gap-1 mt-0.5 ml-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ $logbook->jam_mulai->format('H:i') }} - {{ $logbook->jam_selesai->format('H:i') }}
                                </div>
                            </td>

                            <!-- Kolom Aktivitas -->
                            <td class="px-6 py-4">
                                <div class="mb-2">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $logbook->kategori === 'tugas_utama' ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-purple-50 text-purple-700 border border-purple-200' }} uppercase">
                                        {{ str_replace('_', ' ', $logbook->kategori) }}
                                    </span>
                                    @if($logbook->workProgram)
                                        <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded ml-1" title="{{ $logbook->workProgram->nama_proker }}">
                                            Proker Terkait
                                        </span>
                                    @endif
                                </div>
                                
                                <p class="text-sm text-theme-text font-medium leading-relaxed">{{ $logbook->deskripsi_aktivitas }}</p>
                                
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if($logbook->output)
                                        <span class="text-[11px] text-theme-text bg-theme-surface border border-theme-border px-2 py-1 rounded">
                                            <span class="font-bold">Output:</span> {{ $logbook->output }}
                                        </span>
                                    @endif
                                    
                                    @if($logbook->file_bukti)
                                        <a href="{{ Storage::url($logbook->file_bukti) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-1 rounded hover:underline hover:bg-emerald-100 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                            File Terlampir
                                        </a>
                                    @endif

                                    @if($logbook->link_bukti)
                                        <a href="{{ $logbook->link_bukti }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 border border-blue-200 px-2 py-1 rounded hover:underline hover:bg-blue-100 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                            Link Bukti
                                        </a>
                                    @endif
                                </div>

                                @if($logbook->catatan_verifikator)
                                    <div class="mt-3 p-2 bg-red-50 border border-red-100 rounded text-xs text-red-700">
                                        <strong>Catatan Anda Sebelumnya:</strong> {{ $logbook->catatan_verifikator }}
                                    </div>
                                @endif
                            </td>

                            <!-- Kolom Status -->
                            <!-- PERBAIKAN: Menambahkan informasi "Diperiksa Oleh" -->
                            <td class="px-6 py-4 text-center">
                                @if($logbook->status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200 shadow-sm">
                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse mr-1.5"></span>
                                        Pending
                                    </span>
                                @elseif($logbook->status === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 shadow-sm">
                                        Disetujui
                                    </span>
                                    <div class="mt-1.5 text-[9px] text-theme-muted uppercase tracking-wider">
                                        Oleh: <span class="font-bold text-theme-text">{{ $logbook->verifikator->name ?? 'Sistem' }}</span>
                                    </div>
                                @elseif($logbook->status === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200 shadow-sm">
                                        Revisi
                                    </span>
                                    <div class="mt-1.5 text-[9px] text-theme-muted uppercase tracking-wider">
                                        Oleh: <span class="font-bold text-theme-text">{{ $logbook->verifikator->name ?? 'Sistem' }}</span>
                                    </div>
                                @endif
                            </td>

                            <!-- Kolom Aksi -->
                            <td class="px-6 py-4 text-right">
                                <div class="flex flex-col gap-2 w-full max-w-[120px] ml-auto">
                                    <button wire:click="approve({{ $logbook->id }})" class="w-full flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-500 hover:text-white rounded-lg shadow-sm transition-all {{ $logbook->status === 'approved' ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $logbook->status === 'approved' ? 'disabled' : '' }}>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Setujui
                                    </button>

                                    <button wire:click="openRejectModal({{ $logbook->id }})" class="w-full flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-red-700 bg-red-50 border border-red-200 hover:bg-red-500 hover:text-white rounded-lg shadow-sm transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        Revisi
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-theme-body mb-4 border border-theme-border shadow-inner">
                                    <svg class="w-10 h-10 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <h3 class="text-base font-bold text-theme-text uppercase tracking-tight">Antrean Bersih</h3>
                                <p class="text-sm text-theme-muted mt-1">Tidak ada laporan kinerja yang perlu Anda verifikasi saat ini.</p>
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
    @endif

    <!-- ========================================== -->
    <!-- MODAL PENOLAKAN (CATATAN REVISI) -->
    <!-- ========================================== -->
    @if($isRejectModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm px-4 transition-opacity">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-md overflow-hidden text-left" onclick="event.stopPropagation()">
                <div class="px-6 py-4 border-b border-theme-border bg-red-50/50 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-theme-text">Revisi Logbook</h3>
                        <p class="text-xs text-theme-muted mt-0.5">Beritahu staf apa yang perlu diperbaiki.</p>
                    </div>
                </div>
                
                <form wire:submit.prevent="reject" class="p-6">
                    <div class="mb-5">
                        <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-2">Alasan Penolakan / Catatan <span class="text-red-500">*</span></label>
                        <textarea wire:model="catatan" rows="4" class="block w-full border border-theme-border bg-theme-body rounded-xl py-3 px-4 text-sm focus:ring-red-500 focus:border-red-500 text-theme-text resize-none" placeholder="Contoh: Mohon lampirkan link google drive untuk laporannya..."></textarea>
                        @error('catatan') <span class="text-xs text-red-500 font-medium mt-2 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-theme-border">
                        <button type="button" wire:click="$set('isRejectModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-theme-muted hover:text-theme-text bg-theme-body rounded-xl border border-theme-border transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-md transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"></path></svg>
                            Kirim Revisi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>