<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\WorkProgram;
use App\Models\Unit;
use App\Models\Periode;
use App\Models\PerformanceIndicator;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterPeriodeId = ''; 
    
    // Form States
    public $isModalOpen = false;
    public $prokerId = null;
    public $unit_id;
    public $nama_proker = '';
    public $deskripsi = '';
    public $periode_id = ''; 
    public $anggaran_rencana = 0;
    
    // Indicators mapping
    public $selectedIndicators = []; 
    
    // Data lists
    public $managedUnits = [];
    public $availablePeriodes = [];
    
    // OPTIMASI: Pilihan standar untuk satuan target agar user tidak mengetik manual
    public $satuanOptions = ['%', 'Orang', 'Kegiatan', 'Dokumen', 'Mitra', 'SKS', 'Bulan', 'Publikasi', 'Rupiah', 'Lainnya'];

    public function mount()
    {
        $currentPeriode = Periode::where('is_current', true)->first();
        if ($currentPeriode) {
            $this->filterPeriodeId = $currentPeriode->id;
            $this->periode_id = $currentPeriode->id;
        }
        
        // PERBAIKAN: Menghapus pengecualian Super Admin. 
        // Semua user kini hanya mengelola unit yang dipimpinnya.
        $this->managedUnits = Unit::where('kepala_unit_id', Auth::id())->get();
        
        if($this->managedUnits->count() > 0) {
            $this->unit_id = $this->managedUnits->first()->id;
        }
        
        $this->availablePeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterPeriodeId() { $this->resetPage(); }

    public function openModal($id = null)
    {
        $this->resetValidation();
        $this->reset(['nama_proker', 'deskripsi', 'anggaran_rencana', 'selectedIndicators']);
        
        $currentPeriode = Periode::where('is_current', true)->first();
        $this->periode_id = $currentPeriode ? $currentPeriode->id : '';

        if($this->managedUnits->count() > 0) {
            $this->unit_id = $this->managedUnits->first()->id;
        }

        if ($id) {
            $proker = WorkProgram::with('indicators')->whereIn('unit_id', $this->managedUnits->pluck('id'))->findOrFail($id);
            
            if (in_array($proker->status, ['review_lpm', 'disetujui'])) {
                session()->flash('error', 'Proker tidak dapat diedit karena sedang direview atau sudah disetujui.');
                return;
            }

            $this->prokerId = $id;
            $this->unit_id = $proker->unit_id;
            $this->nama_proker = $proker->nama_proker;
            $this->deskripsi = $proker->deskripsi;
            $this->periode_id = $proker->periode_id;
            $this->anggaran_rencana = $proker->anggaran_rencana;
            
            foreach ($proker->indicators as $ind) {
                // OPTIMASI: Pastikan satuan target ada di list, jika tidak, masukkan ke array options sementara
                $satuan = $ind->pivot->satuan_target;
                if (!in_array($satuan, $this->satuanOptions)) {
                    $this->satuanOptions[] = $satuan;
                }

                $this->selectedIndicators[] = [
                    'id' => $ind->id,
                    'target_angka' => $ind->pivot->target_angka,
                    'satuan_target' => $satuan
                ];
            }
        } else {
            $this->prokerId = null;
            // OPTIMASI: Default array kosong, memaksa user menekan tombol tambah agar UI tidak langsung penuh
            $this->selectedIndicators = [];
        }

        $this->isModalOpen = true;
    }

    public function addIndicator()
    {
        // OPTIMASI: Default satuan target adalah % atau Orang untuk mempercepat input
        $this->selectedIndicators[] = ['id' => '', 'target_angka' => '', 'satuan_target' => 'Orang'];
    }

    public function removeIndicator($index)
    {
        unset($this->selectedIndicators[$index]);
        $this->selectedIndicators = array_values($this->selectedIndicators); // re-index
    }

    public function saveProker($action = 'draft')
    {
        $this->validate([
            'unit_id' => 'required|exists:units,id',
            'nama_proker' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'periode_id' => 'required|exists:periodes,id',
            'anggaran_rencana' => 'required|numeric|min:0',
            'selectedIndicators.*.id' => 'required|exists:performance_indicators,id',
            'selectedIndicators.*.target_angka' => 'required|numeric|min:0',
            'selectedIndicators.*.satuan_target' => 'required|string|max:50',
        ], [
            'selectedIndicators.*.id.required' => 'Indikator harus dipilih.',
            'selectedIndicators.*.target_angka.required' => 'Target angka harus diisi.',
            'anggaran_rencana.min' => 'Anggaran tidak boleh negatif.'
        ]);

        $status = $action === 'submit' ? 'review_lpm' : 'draft';

        $proker = WorkProgram::updateOrCreate(
            ['id' => $this->prokerId],
            [
                'unit_id' => $this->unit_id,
                'nama_proker' => $this->nama_proker,
                'deskripsi' => $this->deskripsi,
                'periode_id' => $this->periode_id,
                'anggaran_rencana' => $this->anggaran_rencana,
                'status' => $status
            ]
        );

        // Sync indicators
        $syncData = [];
        foreach ($this->selectedIndicators as $ind) {
            if(!empty($ind['id']) && $ind['target_angka'] !== '') {
                $syncData[$ind['id']] = [
                    'target_angka' => $ind['target_angka'],
                    'satuan_target' => $ind['satuan_target']
                ];
            }
        }
        $proker->indicators()->sync($syncData);

        $this->isModalOpen = false;
        session()->flash('success', $action === 'submit' ? 'Program kerja berhasil diajukan.' : 'Draft program kerja berhasil disimpan.');
    }

    public function deleteProker($id)
    {
        $proker = WorkProgram::whereIn('unit_id', $this->managedUnits->pluck('id'))->findOrFail($id);
        if (in_array($proker->status, ['review_lpm', 'disetujui'])) {
            session()->flash('error', 'Proker tidak dapat dihapus.');
            return;
        }
        $proker->indicators()->detach();
        $proker->delete();
        session()->flash('success', 'Program kerja berhasil dihapus.');
    }

    public function ajukanProker($id)
    {
        $proker = WorkProgram::whereIn('unit_id', $this->managedUnits->pluck('id'))->findOrFail($id);
        if ($proker->status !== 'draft' && $proker->status !== 'ditolak') {
            return;
        }
        $proker->update(['status' => 'review_lpm']);
        session()->flash('success', 'Program kerja diajukan untuk direview oleh LPM/Pimpinan.');
    }

    public function with(): array
    {
        $query = WorkProgram::with(['unit', 'indicators', 'periode'])
            ->whereIn('unit_id', $this->managedUnits->pluck('id'));

        if ($this->filterPeriodeId) {
            $query->where('periode_id', $this->filterPeriodeId);
        }

        if ($this->search) {
            $query->where('nama_proker', 'like', '%' . $this->search . '%');
        }

        $selectedPeriodeName = 'Semua Periode';
        if ($this->filterPeriodeId) {
            $periode = Periode::find($this->filterPeriodeId);
            if ($periode) {
                $selectedPeriodeName = $periode->nama_periode;
            }
        }

        // OPTIMASI: Mengelompokkan indikator berdasarkan Kategori (IKU vs IKT)
        $indicators = PerformanceIndicator::orderBy('kategori', 'asc')
            ->orderBy('kode_indikator', 'asc')
            ->get()
            ->groupBy('kategori');

        return [
            'prokers' => $query->orderBy('created_at', 'desc')->paginate(10),
            'selectedPeriodeName' => $selectedPeriodeName,
            'groupedIndicators' => $indicators,
        ];
    }
}; ?>

<div class="space-y-6 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-theme-text uppercase tracking-tight">Program Kerja</h1>
            <p class="text-sm font-medium text-theme-muted mt-1">Kelola dan susun rencana kegiatan serta target IKU/IKT unit Anda.</p>
        </div>
        
        <div class="flex items-center gap-3">
            @if($managedUnits->count() > 0)
                <button wire:click="openModal" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-hover text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-primary/30 hover:shadow-primary/50 hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Susun Proker Baru
                </button>
            @endif
        </div>
    </div>

    <!-- Stats & Filters -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <!-- Stats -->
        <div class="md:col-span-4 bg-theme-surface border border-theme-border rounded-2xl p-5 flex items-center justify-between shadow-sm">
            <div>
                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Total Pagu Pengajuan ({{ $selectedPeriodeName }})</p>
                <p class="text-2xl font-black text-primary">
                    Rp {{ number_format($prokers->sum('anggaran_rencana'), 0, ',', '.') }}
                </p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <!-- Filters -->
        <div class="md:col-span-8 bg-theme-surface border border-theme-border rounded-2xl p-4 flex flex-col sm:flex-row items-center gap-3 shadow-sm">
            <div class="w-full sm:w-64">
                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Filter Periode Kinerja</label>
                <select wire:model.live="filterPeriodeId" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-bold cursor-pointer">
                    <option value="">-- Semua Periode --</option>
                    @foreach($availablePeriodes as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_periode }} @if($p->is_current) (Aktif) @endif</option>
                    @endforeach
                </select>
            </div>
            
            <div class="w-full flex-1">
                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Cari Program Kerja</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Ketik nama kegiatan..." class="block w-full pl-10 border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                </div>
            </div>
        </div>
    </div>

    <!-- Proker List -->
    <div class="space-y-4">
        @if($managedUnits->count() === 0)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 flex items-center gap-3">
                <svg class="w-6 h-6 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div class="text-sm">Anda belum ditempatkan sebagai pimpinan unit manapun, sehingga belum dapat mengelola Program Kerja.</div>
            </div>
        @endif

        @if(session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" x-transition class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 text-sm font-bold flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('success') }}
            </div>
        @endif
        
        @if(session()->has('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 text-sm font-bold flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4">
            @forelse($prokers as $proker)
                <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden hover:border-primary/50 hover:shadow-lg transition-all duration-300">
                    <div class="p-5 flex flex-col md:flex-row gap-5">
                        <!-- Left Info -->
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-widest uppercase bg-theme-body text-theme-muted border border-theme-border">
                                    {{ $proker->unit->kode_unit ?? $proker->unit->nama_unit }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-widest uppercase {{ 
                                    $proker->status === 'disetujui' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 
                                    ($proker->status === 'ditolak' ? 'bg-rose-50 text-rose-600 border border-rose-200' : 
                                    ($proker->status === 'review_lpm' ? 'bg-amber-50 text-amber-600 border border-amber-200' : 'bg-slate-100 text-slate-600 border border-slate-200'))
                                }}">
                                    {{ str_replace('_', ' ', $proker->status) }}
                                </span>
                            </div>
                            
                            <h3 class="text-lg font-black text-theme-text mb-1">{{ $proker->nama_proker }}</h3>
                            <p class="text-sm text-theme-muted mb-4 line-clamp-2 leading-relaxed">{{ $proker->deskripsi ?: 'Tidak ada deskripsi.' }}</p>
                            
                            <!-- Indicators Mapping List -->
                            <div class="space-y-1.5 mt-2 pt-3 border-t border-theme-border">
                                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-2">Pemetaan Target (IKU/IKT):</p>
                                @if($proker->indicators->count() > 0)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($proker->indicators as $ind)
                                            <div class="inline-flex items-center bg-primary/5 border border-primary/20 rounded-lg overflow-hidden group">
                                                <span class="px-2.5 py-1 text-[10px] font-black text-white bg-primary uppercase" title="{{ $ind->nama_indikator }}">
                                                    {{ $ind->kode_indikator }}
                                                </span>
                                                <span class="px-2.5 py-1 text-xs font-bold text-theme-text">
                                                    {{ $ind->pivot->target_angka }} <span class="text-theme-muted text-[10px] ml-0.5">{{ $ind->pivot->satuan_target }}</span>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="inline-block px-3 py-1 bg-rose-50 border border-rose-100 rounded-md text-xs font-semibold text-rose-600">
                                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        Belum ada IKU/IKT yang dihubungkan!
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Right Actions & Stats -->
                        <div class="md:w-64 flex flex-col md:items-end justify-between border-t md:border-t-0 md:border-l border-theme-border pt-4 md:pt-0 md:pl-5 gap-4">
                            <div class="text-left md:text-right w-full">
                                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-0.5">Pagu Anggaran</p>
                                <p class="text-xl font-black text-theme-text font-mono">Rp {{ number_format($proker->anggaran_rencana, 0, ',', '.') }}</p>
                            </div>
                            
                            <div class="flex items-center gap-2 w-full md:justify-end">
                                @if($proker->status === 'draft' || $proker->status === 'ditolak')
                                    <button wire:click="openModal({{ $proker->id }})" class="p-2 bg-theme-body text-theme-muted hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    
                                    <button wire:click="deleteProker({{ $proker->id }})" wire:confirm="Anda yakin ingin menghapus draft kegiatan ini permanen?" class="p-2 bg-theme-body text-theme-muted hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus Permanen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    
                                    <button wire:click="ajukanProker({{ $proker->id }})" wire:confirm="Pastikan IKU & Anggaran sudah benar. Ajukan ke LPM sekarang?" class="px-3 py-2 bg-primary/10 hover:bg-primary text-primary hover:text-white text-xs font-bold rounded-lg transition-colors ml-auto md:ml-0" title="Kirim untuk Verifikasi">
                                        Ajukan Review &rarr;
                                    </button>
                                @else
                                    <button wire:click="openModal({{ $proker->id }})" class="p-2 bg-theme-body text-theme-muted hover:text-primary rounded-lg transition-colors ml-auto md:ml-0" title="Lihat Mode Read-Only">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-theme-surface border border-theme-border rounded-2xl p-12 text-center shadow-sm">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-theme-body mb-4">
                        <svg class="w-8 h-8 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-theme-text mb-1">Belum Ada Program Kerja</h3>
                    <p class="text-theme-muted text-sm max-w-md mx-auto">Unit Anda belum menyusun rencana program kerja untuk periode ini. Silakan mulai dengan menekan tombol "Susun Proker Baru".</p>
                </div>
            @endforelse
        </div>
        
        <div class="mt-4">
            {{ $prokers->links() }}
        </div>
    </div>

    <!-- Modal Form -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-2xl w-full max-w-5xl flex flex-col max-h-[90vh] overflow-hidden" @click.stop>
                <!-- Header -->
                <div class="px-6 py-5 border-b border-theme-border bg-theme-body/30 flex justify-between items-center z-10 shrink-0">
                    <div>
                        <p class="text-[10px] font-bold text-primary uppercase tracking-widest mb-0.5">Formulir Rencana</p>
                        <h3 class="text-xl font-black text-theme-text">{{ $prokerId ? 'Edit Program Kerja' : 'Susun Program Kerja' }}</h3>
                    </div>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="p-2 bg-theme-surface border border-theme-border text-theme-muted hover:text-rose-500 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- Body -->
                <form class="flex flex-col overflow-hidden">
                    <div class="p-6 overflow-y-auto flex-1 space-y-8 bg-theme-body/10">
                        
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                            <!-- Kolom Kiri: Data Utama Proker (Lebar 5 kolom) -->
                            <div class="lg:col-span-5 space-y-5">
                                <h4 class="text-sm font-black text-theme-text border-b border-theme-border pb-2 uppercase tracking-wide">1. Informasi Dasar</h4>
                                
                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-1.5">Unit Pengusul <span class="text-rose-500">*</span></label>
                                    <select wire:model="unit_id" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-bold">
                                        @foreach($managedUnits as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                                        @endforeach
                                    </select>
                                    @error('unit_id') <span class="text-xs font-semibold text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-1.5">Nama Kegiatan / Proker <span class="text-rose-500">*</span></label>
                                    <input type="text" wire:model="nama_proker" placeholder="Cth: Seminar Nasional Teknologi..." class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-bold">
                                    @error('nama_proker') <span class="text-xs font-semibold text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2 sm:col-span-1">
                                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-1.5">Tahun Akademik <span class="text-rose-500">*</span></label>
                                        <select wire:model.live="periode_id" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-bold">
                                            <option value="">-- Pilih --</option>
                                            @foreach($availablePeriodes as $p)
                                                <option value="{{ $p->id }}">{{ $p->nama_periode }}</option>
                                            @endforeach
                                        </select>
                                        @error('periode_id') <span class="text-xs font-semibold text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <!-- OPTIMASI: Input Anggaran dengan Format Rupiah Otomatis menggunakan Alpine -->
                                    <div class="col-span-2 sm:col-span-1" x-data="{ 
                                            formatRupiah(value) { 
                                                if(!value) return 'Rp 0';
                                                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
                                            } 
                                        }">
                                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-1.5">Pagu Anggaran <span class="text-rose-500">*</span></label>
                                        <input type="number" wire:model.live.debounce.500ms="anggaran_rencana" min="0" placeholder="0" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-mono font-bold">
                                        
                                        <!-- Realtime Formatter Text -->
                                        <p class="text-xs font-bold text-emerald-600 mt-1.5 bg-emerald-50 px-2 py-1 rounded-md border border-emerald-100" x-text="formatRupiah($wire.anggaran_rencana)"></p>
                                        @error('anggaran_rencana') <span class="text-xs font-semibold text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-1.5">Deskripsi Singkat</label>
                                    <textarea wire:model="deskripsi" rows="3" placeholder="Tujuan dan gambaran singkat kegiatan..." class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text"></textarea>
                                </div>
                            </div>

                            <!-- Kolom Kanan: Mapping Indikator (Lebar 7 kolom) -->
                            <div class="lg:col-span-7 bg-theme-surface p-6 rounded-2xl border-2 border-theme-border shadow-sm">
                                <div class="flex items-center justify-between mb-5 border-b border-theme-border pb-3">
                                    <div>
                                        <h4 class="text-sm font-black text-theme-text uppercase tracking-wide">2. Pemetaan Target (IKU/IKT)</h4>
                                        <p class="text-[10px] text-theme-muted uppercase mt-1">Hubungkan kegiatan ini dengan standar kinerja.</p>
                                    </div>
                                    <button type="button" wire:click="addIndicator" class="px-3 py-1.5 bg-primary/10 text-primary border border-primary/20 hover:bg-primary hover:text-white rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                                        Tambah
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    @foreach($selectedIndicators as $index => $indicator)
                                        <div class="bg-theme-body/50 border border-theme-border p-3.5 rounded-xl relative group flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                                            
                                            <!-- Tombol Hapus Baris -->
                                            <button type="button" wire:click="removeIndicator({{ $index }})" class="absolute -top-2.5 -right-2.5 w-6 h-6 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center sm:opacity-0 group-hover:opacity-100 transition-opacity border border-rose-200 shadow-sm" title="Hapus baris">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                            
                                            <!-- OPTIMASI: Select Indikator menggunakan OptGroup -->
                                            <div class="w-full sm:flex-1">
                                                <label class="block text-[9px] font-bold text-theme-muted uppercase mb-1">Pilih Indikator</label>
                                                <select wire:model="selectedIndicators.{{ $index }}.id" class="block w-full border-theme-border bg-theme-surface rounded-lg py-2 px-2.5 text-xs focus:ring-primary focus:border-primary text-theme-text font-semibold">
                                                    <option value="">-- Pilih Indikator Target --</option>
                                                    @foreach($groupedIndicators as $kategori => $inds)
                                                        <optgroup label="Standar {{ $kategori }}">
                                                            @foreach($inds as $avail)
                                                                <option value="{{ $avail->id }}">[{{ $avail->kode_indikator }}] {{ Str::limit($avail->nama_indikator, 50) }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                                @error('selectedIndicators.'.$index.'.id') <span class="text-[10px] font-bold text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                            
                                            <!-- OPTIMASI: Kolom Angka dan Dropdown Satuan -->
                                            <div class="w-full sm:w-48 flex gap-2">
                                                <div class="w-1/2">
                                                    <label class="block text-[9px] font-bold text-theme-muted uppercase mb-1">Nilai</label>
                                                    <input type="number" step="0.01" wire:model="selectedIndicators.{{ $index }}.target_angka" placeholder="0" class="block w-full border-theme-border bg-theme-surface rounded-lg py-2 px-2.5 text-xs focus:ring-primary focus:border-primary text-theme-text font-mono font-bold text-center">
                                                    @error('selectedIndicators.'.$index.'.target_angka') <span class="text-[10px] font-bold text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="w-1/2">
                                                    <label class="block text-[9px] font-bold text-theme-muted uppercase mb-1">Satuan</label>
                                                    <select wire:model="selectedIndicators.{{ $index }}.satuan_target" class="block w-full border-theme-border bg-theme-surface rounded-lg py-2 px-2.5 text-xs focus:ring-primary focus:border-primary text-theme-text font-semibold">
                                                        <option value="">-- Satuan --</option>
                                                        @foreach($satuanOptions as $opt)
                                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('selectedIndicators.'.$index.'.satuan_target') <span class="text-[10px] font-bold text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                        </div>
                                    @endforeach
                                    
                                    <!-- Empty State jika array selectedIndicators kosong -->
                                    @if(count($selectedIndicators) === 0)
                                        <div class="text-center py-8 border-2 border-dashed border-theme-border rounded-xl bg-theme-body/30">
                                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-theme-surface border border-theme-border mb-2 text-theme-muted">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                            </div>
                                            <p class="text-xs text-theme-muted font-bold uppercase tracking-wider mb-2">Belum Ada Target</p>
                                            <p class="text-[10px] text-theme-muted max-w-xs mx-auto mb-3">Klik tombol "Tambah" di kanan atas untuk mulai memetakan kegiatan ini ke standar IKU/IKT Institusi.</p>
                                            <button type="button" wire:click="addIndicator" class="px-3 py-1.5 bg-primary text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                                                Mulai Petakan
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer Actions -->
                    <div class="px-6 py-4 border-t border-theme-border bg-theme-surface flex justify-end gap-3 shrink-0 items-center">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-5 py-2.5 text-xs font-bold text-theme-muted hover:text-theme-text transition-colors">
                            Batal
                        </button>
                        
                        @php
                            $proker = $prokerId ? WorkProgram::find($prokerId) : null;
                            $canEdit = !$proker || in_array($proker->status, ['draft', 'ditolak']);
                        @endphp
                        
                        @if($canEdit)
                            <button type="button" wire:click="saveProker('draft')" class="px-5 py-2.5 bg-theme-body border-2 border-theme-border hover:border-primary text-theme-text text-xs font-bold rounded-xl transition-all shadow-sm">
                                Simpan Sebagai Draft
                            </button>
                            <button type="button" wire:click="saveProker('submit')" class="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-primary/30 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                Simpan & Ajukan
                            </button>
                        @else
                            <p class="text-[10px] font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200">
                                Proker sedang dalam reviu atau telah disetujui. Mode Read-Only.
                            </p>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>