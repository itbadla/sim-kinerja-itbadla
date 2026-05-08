<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\WorkProgram;
use App\Models\Unit;
use App\Models\PerformanceIndicator;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterTahun = '';
    
    // Form States
    public $isModalOpen = false;
    public $prokerId = null;
    public $unit_id;
    public $nama_proker = '';
    public $deskripsi = '';
    public $tahun_anggaran;
    public $anggaran_rencana = 0;
    
    // Indicators mapping
    public $selectedIndicators = []; // array of ['id' => x, 'target_angka' => y, 'satuan_target' => z]
    
    // Data lists
    public $managedUnits = [];
    public $availableIndicators = [];

    public function mount()
    {
        $this->filterTahun = date('Y');
        $this->tahun_anggaran = date('Y');
        
        // Pimpinan unit (Dekan, Kaprodi, Kepala Lembaga dll) can manage proker for their unit
        // Or if they are super admin they could potentially see all, but let's stick to unit-based
        if (Auth::user()->hasRole('Super Admin')) {
            $this->managedUnits = Unit::all();
        } else {
            $this->managedUnits = Unit::where('kepala_unit_id', Auth::id())->get();
        }
        
        if($this->managedUnits->count() > 0) {
            $this->unit_id = $this->managedUnits->first()->id;
        }
        
        $this->availableIndicators = PerformanceIndicator::all();
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterTahun() { $this->resetPage(); }

    public function openModal($id = null)
    {
        $this->resetValidation();
        $this->reset(['nama_proker', 'deskripsi', 'anggaran_rencana', 'selectedIndicators']);
        $this->tahun_anggaran = date('Y');
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
            $this->tahun_anggaran = $proker->tahun_anggaran;
            $this->anggaran_rencana = $proker->anggaran_rencana;
            
            foreach ($proker->indicators as $ind) {
                $this->selectedIndicators[] = [
                    'id' => $ind->id,
                    'target_angka' => $ind->pivot->target_angka,
                    'satuan_target' => $ind->pivot->satuan_target
                ];
            }
        } else {
            $this->prokerId = null;
            $this->selectedIndicators = [
                ['id' => '', 'target_angka' => '', 'satuan_target' => '']
            ];
        }

        $this->isModalOpen = true;
    }

    public function addIndicator()
    {
        $this->selectedIndicators[] = ['id' => '', 'target_angka' => '', 'satuan_target' => ''];
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
            'tahun_anggaran' => 'required|digits:4',
            'anggaran_rencana' => 'required|numeric|min:0',
            'selectedIndicators.*.id' => 'required|exists:performance_indicators,id',
            'selectedIndicators.*.target_angka' => 'required|numeric|min:0',
            'selectedIndicators.*.satuan_target' => 'required|string|max:50',
        ]);

        $status = $action === 'submit' ? 'review_lpm' : 'draft';

        $proker = WorkProgram::updateOrCreate(
            ['id' => $this->prokerId],
            [
                'unit_id' => $this->unit_id,
                'nama_proker' => $this->nama_proker,
                'deskripsi' => $this->deskripsi,
                'tahun_anggaran' => $this->tahun_anggaran,
                'anggaran_rencana' => $this->anggaran_rencana,
                'status' => $status
            ]
        );

        // Sync indicators
        $syncData = [];
        foreach ($this->selectedIndicators as $ind) {
            if(!empty($ind['id'])) {
                $syncData[$ind['id']] = [
                    'target_angka' => $ind['target_angka'],
                    'satuan_target' => $ind['satuan_target']
                ];
            }
        }
        $proker->indicators()->sync($syncData);

        $this->isModalOpen = false;
        session()->flash('success', 'Program kerja berhasil disimpan.');
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
        $query = WorkProgram::with('unit', 'indicators')
            ->whereIn('unit_id', $this->managedUnits->pluck('id'));

        if ($this->filterTahun) {
            $query->where('tahun_anggaran', $this->filterTahun);
        }

        if ($this->search) {
            $query->where('nama_proker', 'like', '%' . $this->search . '%');
        }

        return [
            'prokers' => $query->orderBy('created_at', 'desc')->paginate(10),
        ];
    }
}; ?>

<div class="space-y-6">
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
                    Susun Proker
                </button>
            @endif
        </div>
    </div>

    <!-- Stats & Filters -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <!-- Stats -->
        <div class="md:col-span-4 bg-theme-surface border border-theme-border rounded-2xl p-5 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Total Anggaran ({{ $filterTahun ?: 'Semua' }})</p>
                <p class="text-2xl font-black text-primary">
                    Rp {{ number_format($prokers->sum('anggaran_rencana'), 0, ',', '.') }}
                </p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        <!-- Filters -->
        <div class="md:col-span-8 bg-theme-surface border border-theme-border rounded-2xl p-4 flex flex-col sm:flex-row items-center gap-3">
            <div class="w-full sm:w-48">
                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Tahun Anggaran</label>
                <select wire:model.live="filterTahun" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-mono">
                    <option value="">Semua Tahun</option>
                    @for($y = date('Y') - 1; $y <= date('Y') + 2; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            
            <div class="w-full flex-1">
                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Cari Program Kerja</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama kegiatan..." class="block w-full pl-10 border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                </div>
            </div>
        </div>
    </div>

    <!-- Proker List -->
    <div class="space-y-4">
        @if($managedUnits->count() === 0)
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4 flex items-center gap-3">
                <svg class="w-6 h-6 text-yellow-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div class="text-sm">Anda tidak memimpin unit manapun, sehingga tidak dapat mengelola Program Kerja.</div>
            </div>
        @endif

        @if(session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 text-sm font-medium flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('success') }}
            </div>
        @endif
        
        @if(session()->has('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 text-sm font-medium flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4">
            @forelse($prokers as $proker)
                <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden hover:shadow-lg transition-all duration-300">
                    <div class="p-5 flex flex-col md:flex-row gap-5">
                        <!-- Left Info -->
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-widest uppercase bg-theme-body text-theme-muted border border-theme-border">
                                    {{ $proker->unit->nama_unit }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-widest uppercase {{ 
                                    $proker->status === 'disetujui' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 
                                    ($proker->status === 'ditolak' ? 'bg-red-100 text-red-700 border border-red-200' : 
                                    ($proker->status === 'review_lpm' ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200'))
                                }}">
                                    {{ str_replace('_', ' ', $proker->status) }}
                                </span>
                            </div>
                            
                            <h3 class="text-lg font-bold text-theme-text mb-1">{{ $proker->nama_proker }}</h3>
                            <p class="text-sm text-theme-muted mb-4 line-clamp-2">{{ $proker->deskripsi ?: 'Tidak ada deskripsi' }}</p>
                            
                            <!-- Indicators -->
                            @if($proker->indicators->count() > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($proker->indicators as $ind)
                                        <div class="inline-flex items-center gap-1.5 bg-primary/5 border border-primary/10 rounded-lg px-2.5 py-1">
                                            <span class="text-[10px] font-bold text-primary uppercase">{{ $ind->kode_indikator }}</span>
                                            <span class="w-1 h-1 rounded-full bg-primary/30"></span>
                                            <span class="text-xs font-medium text-theme-text">{{ $ind->pivot->target_angka }} {{ $ind->pivot->satuan_target }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs italic text-theme-muted">Belum ada target IKU/IKT yang dipetakan.</span>
                            @endif
                        </div>

                        <!-- Right Actions & Stats -->
                        <div class="md:w-64 flex flex-col md:items-end justify-between border-t md:border-t-0 md:border-l border-theme-border pt-4 md:pt-0 md:pl-5 gap-4">
                            <div class="text-left md:text-right w-full">
                                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-0.5">Anggaran ({{ $proker->tahun_anggaran }})</p>
                                <p class="text-lg font-black text-theme-text">Rp {{ number_format($proker->anggaran_rencana, 0, ',', '.') }}</p>
                            </div>
                            
                            <div class="flex items-center gap-2 w-full md:justify-end">
                                @if($proker->status === 'draft' || $proker->status === 'ditolak')
                                    <button wire:click="openModal({{ $proker->id }})" class="p-2 bg-theme-body text-theme-muted hover:text-primary rounded-lg transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    
                                    <button wire:click="deleteProker({{ $proker->id }})" wire:confirm="Yakin ingin menghapus program kerja ini?" class="p-2 bg-theme-body text-theme-muted hover:text-red-500 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    
                                    <button wire:click="ajukanProker({{ $proker->id }})" wire:confirm="Ajukan proker ini untuk direview oleh pimpinan?" class="px-3 py-2 bg-primary/10 hover:bg-primary text-primary hover:text-white text-xs font-bold rounded-lg transition-colors ml-auto md:ml-0" title="Ajukan">
                                        Ajukan Review
                                    </button>
                                @else
                                    <button wire:click="openModal({{ $proker->id }})" class="p-2 bg-theme-body text-theme-muted hover:text-primary rounded-lg transition-colors ml-auto md:ml-0" title="Lihat Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-theme-surface border border-theme-border rounded-2xl p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-theme-body mb-4">
                        <svg class="w-8 h-8 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-theme-text mb-1">Belum Ada Program Kerja</h3>
                    <p class="text-theme-muted text-sm max-w-md mx-auto">Unit Anda belum menyusun rencana program kerja untuk tahun ini. Silakan mulai dengan menekan tombol "Susun Proker".</p>
                </div>
            @endforelse
        </div>
        
        <div class="mt-4">
            {{ $prokers->links() }}
        </div>
    </div>

    <!-- Modal Form -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center z-10 shrink-0">
                    <h3 class="text-lg font-bold text-theme-text">{{ $prokerId ? 'Detail/Edit Program Kerja' : 'Susun Program Kerja Baru' }}</h3>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- Body -->
                <form class="flex flex-col overflow-hidden">
                    <div class="p-6 overflow-y-auto custom-scrollbar flex-1 space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Kolom Kiri: Data Utama Proker -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Pilih Unit Pengusul <span class="text-red-500">*</span></label>
                                    <select wire:model="unit_id" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                        @foreach($managedUnits as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                                        @endforeach
                                    </select>
                                    @error('unit_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Nama Kegiatan / Proker <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="nama_proker" placeholder="Contoh: Pengadaan Alat Laboratorium..." class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                    @error('nama_proker') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Deskripsi Singkat</label>
                                    <textarea wire:model="deskripsi" rows="3" placeholder="Tujuan dan gambaran singkat kegiatan..." class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text"></textarea>
                                    @error('deskripsi') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Tahun Anggaran <span class="text-red-500">*</span></label>
                                        <input type="number" wire:model="tahun_anggaran" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-mono">
                                        @error('tahun_anggaran') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Pagu Anggaran (Rp) <span class="text-red-500">*</span></label>
                                        <input type="number" wire:model="anggaran_rencana" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-mono">
                                        @error('anggaran_rencana') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Kolom Kanan: Mapping Indikator -->
                            <div class="bg-theme-body/30 p-5 rounded-2xl border border-theme-border">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h4 class="text-sm font-bold text-theme-text">Target Indikator (IKU/IKT)</h4>
                                        <p class="text-[10px] text-theme-muted uppercase tracking-wider mt-0.5">Pemetaan Output Program</p>
                                    </div>
                                    <button type="button" wire:click="addIndicator" class="px-2.5 py-1 bg-primary/10 text-primary hover:bg-primary hover:text-white rounded text-xs font-bold transition-colors">
                                        + Tambah Target
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    @foreach($selectedIndicators as $index => $indicator)
                                        <div class="bg-theme-surface border border-theme-border p-3 rounded-xl relative group">
                                            <button type="button" wire:click="removeIndicator({{ $index }})" class="absolute -top-2 -right-2 w-6 h-6 bg-red-100 text-red-600 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity border border-red-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                            
                                            <div class="space-y-2.5">
                                                <div>
                                                    <select wire:model="selectedIndicators.{{ $index }}.id" class="block w-full border-theme-border bg-theme-body rounded-lg py-1.5 px-2.5 text-xs focus:ring-primary focus:border-primary text-theme-text">
                                                        <option value="">-- Pilih Indikator IKU/IKT --</option>
                                                        @foreach($availableIndicators as $avail)
                                                            <option value="{{ $avail->id }}">[{{ $avail->kode_indikator }}] {{ Str::limit($avail->nama_indikator, 40) }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('selectedIndicators.'.$index.'.id') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                                </div>
                                                
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <input type="number" step="0.01" wire:model="selectedIndicators.{{ $index }}.target_angka" placeholder="Angka Target" class="block w-full border-theme-border bg-theme-body rounded-lg py-1.5 px-2.5 text-xs focus:ring-primary focus:border-primary text-theme-text font-mono">
                                                        @error('selectedIndicators.'.$index.'.target_angka') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                                    </div>
                                                    <div>
                                                        <input type="text" wire:model="selectedIndicators.{{ $index }}.satuan_target" placeholder="Satuan (%, Dok, Mitra)" class="block w-full border-theme-border bg-theme-body rounded-lg py-1.5 px-2.5 text-xs focus:ring-primary focus:border-primary text-theme-text">
                                                        @error('selectedIndicators.'.$index.'.satuan_target') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    
                                    @if(count($selectedIndicators) === 0)
                                        <div class="text-center py-4 border-2 border-dashed border-theme-border rounded-xl">
                                            <p class="text-xs text-theme-muted">Belum ada target indikator.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">
                            Tutup
                        </button>
                        
                        @php
                            $proker = $prokerId ? WorkProgram::find($prokerId) : null;
                            $canEdit = !$proker || in_array($proker->status, ['draft', 'ditolak']);
                        @endphp
                        
                        @if($canEdit)
                            <button type="button" wire:click="saveProker('draft')" class="px-4 py-2 bg-theme-surface border border-theme-border hover:border-primary text-theme-text text-sm font-bold rounded-xl transition-all">
                                Simpan Draft
                            </button>
                            <button type="button" wire:click="saveProker('submit')" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white text-sm font-bold rounded-xl transition-all shadow-md shadow-primary/20">
                                Simpan & Ajukan
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
