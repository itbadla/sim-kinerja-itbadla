<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\WorkProgram;
use App\Models\Periode;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterStatus = 'review_lpm';
    public $selectedPeriodeId = ''; // State untuk Dropdown Periode
    
    // Modal Details
    public $isModalOpen = false;
    public $selectedProker = null;

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

    public function openDetailModal($id)
    {
        // Pastikan memuat relasi 'periode' juga
        $this->selectedProker = WorkProgram::with(['unit', 'indicators', 'periode'])->findOrFail($id);
        $this->isModalOpen = true;
    }

    public function setujuProker($id)
    {
        $proker = WorkProgram::findOrFail($id);
        if ($proker->status === 'review_lpm') {
            $proker->update(['status' => 'disetujui']);
            session()->flash('success', 'Program Kerja [' . $proker->nama_proker . '] telah DISETUJUI.');
            if ($this->selectedProker && $this->selectedProker->id == $id) {
                $this->isModalOpen = false;
            }
        }
    }

    public function tolakProker($id)
    {
        $proker = WorkProgram::findOrFail($id);
        if ($proker->status === 'review_lpm') {
            $proker->update(['status' => 'ditolak']);
            session()->flash('error', 'Program Kerja [' . $proker->nama_proker . '] telah DITOLAK.');
            if ($this->selectedProker && $this->selectedProker->id == $id) {
                $this->isModalOpen = false;
            }
        }
    }

    public function with(): array
    {
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $prokers = collect();

        // Hanya tampilkan data jika ada periode yang dipilih
        if ($this->selectedPeriodeId) {
            $query = WorkProgram::with(['unit', 'indicators', 'periode'])
                                ->where('periode_id', $this->selectedPeriodeId);

            if ($this->filterStatus) {
                $query->where('status', $this->filterStatus);
            }

            if ($this->search) {
                $query->where(function ($q) {
                    $q->whereHas('unit', function ($qu) {
                        $qu->where('nama_unit', 'like', '%' . $this->search . '%');
                    })->orWhere('nama_proker', 'like', '%' . $this->search . '%');
                });
            }

            $prokers = $query->orderBy('updated_at', 'desc')->paginate(10);
        }

        return [
            'prokers' => $prokers,
            'allPeriodes' => $allPeriodes,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header Section & Dropdown Periode -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-theme-text uppercase tracking-tight">Verifikasi Program Kerja</h1>
            <p class="text-sm font-medium text-theme-muted mt-1">Evaluasi dan berikan persetujuan untuk rencana kegiatan (Proker) unit kerja.</p>
        </div>
        
        <!-- Dropdown Filter Periode -->
        <div class="w-full sm:w-64">
            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Pilih Periode Kinerja</label>
            <select wire:model.live="selectedPeriodeId" class="w-full border-gray-300 bg-white rounded-xl text-sm font-bold text-gray-900 focus:ring-primary focus:border-primary shadow-sm cursor-pointer">
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

    <!-- Filters -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl p-4 flex flex-col sm:flex-row items-center gap-3 shadow-sm">
        <div class="w-full sm:w-48">
            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Status Verifikasi</label>
            <select wire:model.live="filterStatus" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-bold">
                <option value="">Semua Status</option>
                <option value="review_lpm">Menunggu Review</option>
                <option value="disetujui">Telah Disetujui</option>
                <option value="ditolak">Ditolak / Revisi</option>
                <option value="draft">Masih Draft</option>
            </select>
        </div>
        
        <div class="w-full flex-1">
            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Pencarian Cepat</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama unit atau judul proker..." class="block w-full pl-10 border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
            </div>
        </div>
    </div>

    <!-- Warning Jika Belum Pilih Periode -->
    @if(!$selectedPeriodeId)
        <div class="bg-amber-50 border border-amber-200 text-amber-700 rounded-xl p-4 text-sm font-medium flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            Silakan pilih Periode Kinerja terlebih dahulu untuk memverifikasi program kerja.
        </div>
    @endif

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 text-sm font-medium flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 text-sm font-medium flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- Table List -->
    @if($selectedPeriodeId)
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-theme-text">
                <thead class="bg-theme-body/50 text-theme-muted uppercase tracking-wider text-[10px] font-bold border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 border-b border-theme-border">Unit Pengusul</th>
                        <th class="px-6 py-4 border-b border-theme-border w-1/3">Nama Program Kerja</th>
                        <th class="px-6 py-4 border-b border-theme-border text-center">Periode / Anggaran</th>
                        <th class="px-6 py-4 border-b border-theme-border text-center">Status</th>
                        <th class="px-6 py-4 border-b border-theme-border text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($prokers as $proker)
                        <tr class="hover:bg-theme-body/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-theme-text">{{ $proker->unit->nama_unit ?? 'Unit Tidak Ditemukan' }}</div>
                                <div class="text-[10px] font-mono text-primary font-bold uppercase tracking-widest mt-0.5">{{ $proker->unit->kode_unit ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-theme-text mb-1">{{ $proker->nama_proker }}</div>
                                <div class="text-xs text-theme-muted line-clamp-1">{{ $proker->deskripsi }}</div>
                                @if($proker->indicators->count() > 0)
                                    <div class="mt-2 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 inline-block">
                                        {{ $proker->indicators->count() }} IKU/IKT Terpetakan
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="font-bold text-theme-text">{{ $proker->periode->nama_periode ?? 'N/A' }}</div>
                                <div class="text-xs text-theme-muted font-mono mt-0.5">Rp {{ number_format($proker->anggaran_rencana, 0, ',', '.') }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($proker->status === 'review_lpm')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200 uppercase tracking-widest">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Menunggu Review
                                    </span>
                                @elseif($proker->status === 'disetujui')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200 uppercase tracking-widest">
                                        Disetujui
                                    </span>
                                @elseif($proker->status === 'ditolak')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold bg-red-100 text-red-700 border border-red-200 uppercase tracking-widest">
                                        Ditolak / Revisi
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 uppercase tracking-widest">
                                        Draft
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="openDetailModal({{ $proker->id }})" class="p-2 text-theme-muted hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Lihat Detail">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    
                                    @if($proker->status === 'review_lpm')
                                        <button wire:click="setujuProker({{ $proker->id }})" wire:confirm="Setujui program kerja ini?" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Setujui">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        </button>
                                        <button wire:click="tolakProker({{ $proker->id }})" wire:confirm="Tolak/minta revisi untuk program kerja ini?" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Tolak / Revisi">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-theme-body mb-4">
                                    <svg class="w-8 h-8 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <h3 class="text-lg font-bold text-theme-text mb-1">Tidak Ada Data Ditemukan</h3>
                                <p class="text-theme-muted text-sm max-w-md mx-auto">Sesuai dengan filter yang Anda gunakan, belum ada proker yang perlu ditinjau pada periode ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($prokers->hasPages())
            <div class="px-6 py-4 border-t border-theme-border">
                {{ $prokers->links() }}
            </div>
        @endif
    </div>
    @endif

    <!-- Modal Detail -->
    @if($isModalOpen && $selectedProker)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center z-10 shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-theme-text uppercase tracking-tight">Detail Program Kerja</h3>
                        <p class="text-xs text-theme-muted mt-0.5">Peninjauan rencana kerja dan anggaran unit.</p>
                    </div>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto custom-scrollbar flex-1 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Unit Pengusul</p>
                                <p class="text-sm font-bold text-theme-text">{{ $selectedProker->unit->nama_unit }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Nama Kegiatan</p>
                                <p class="text-base font-bold text-theme-text">{{ $selectedProker->nama_proker }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Deskripsi Kegiatan</p>
                                <p class="text-sm text-theme-text">{{ $selectedProker->deskripsi ?: '-' }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-theme-border">
                                <div>
                                    <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Periode Kinerja</p>
                                    <p class="text-sm font-bold text-theme-text font-mono">{{ $selectedProker->periode->nama_periode ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Pagu Anggaran</p>
                                    <p class="text-sm font-bold text-primary font-mono">Rp {{ number_format($selectedProker->anggaran_rencana, 0, ',', '.') }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-theme-body/50 rounded-xl border border-theme-border p-4">
                            <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-3">Target Indikator (IKU/IKT)</p>
                            
                            @if($selectedProker->indicators->count() > 0)
                                <div class="space-y-3">
                                    @foreach($selectedProker->indicators as $ind)
                                        <div class="bg-theme-surface border border-theme-border rounded-lg p-3">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-widest uppercase bg-primary/10 text-primary">{{ $ind->kode_indikator }}</span>
                                            </div>
                                            <p class="text-xs text-theme-text font-medium mb-2">{{ $ind->nama_indikator }}</p>
                                            <div class="flex items-center justify-between border-t border-theme-border pt-2 mt-2">
                                                <span class="text-[10px] text-theme-muted uppercase tracking-wider">Target:</span>
                                                <span class="text-xs font-bold text-theme-text font-mono">{{ $ind->pivot->target_angka }} {{ $ind->pivot->satuan_target }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6 text-theme-muted text-xs italic">
                                    Tidak ada indikator yang dilampirkan.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-end gap-3 shrink-0">
                    <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">
                        Tutup
                    </button>
                    
                    @if($selectedProker->status === 'review_lpm')
                        <button type="button" wire:click="tolakProker({{ $selectedProker->id }})" wire:confirm="Tolak/minta revisi proker ini?" class="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white border border-red-200 text-sm font-bold rounded-xl transition-all">
                            Tolak / Revisi
                        </button>
                        <button type="button" wire:click="setujuProker({{ $selectedProker->id }})" wire:confirm="Setujui proker ini?" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold rounded-xl transition-all shadow-md shadow-emerald-500/20">
                            Setujui Proker
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>