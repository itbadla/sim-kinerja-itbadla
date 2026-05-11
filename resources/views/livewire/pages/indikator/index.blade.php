<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\PerformanceIndicator;
use App\Models\Periode;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] class extends Component {
    // State untuk Form & Filter
    public $search = '';
    public $selectedPeriodeId = '';
    public $isOpen = false;
    public $isEdit = false;
    
    // Properties Model
    public $indikatorId;
    public $kode_indikator;
    public $nama_indikator;
    public $kategori = 'IKU';

    public function mount()
    {
        // Set Default Periode ke yang sedang aktif
        $currentPeriode = Periode::where('is_current', true)->first();
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }
    }

    public function updatingSearch() { }
    public function updatingSelectedPeriodeId() { $this->resetForm(); }

    // Ambil data untuk tabel
    public function with(): array
    {
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);
        
        $indicators = collect();
        if ($selectedPeriode) {
            $indicators = PerformanceIndicator::where('periode_id', $this->selectedPeriodeId)
                ->where(function($q) {
                    $q->where('nama_indikator', 'like', '%' . $this->search . '%')
                      ->orWhere('kode_indikator', 'like', '%' . $this->search . '%');
                })
                ->orderBy('kategori', 'desc')
                ->orderBy('kode_indikator', 'asc')
                ->get();
        }

        return [
            'indicators' => $indicators,
            'allPeriodes' => $allPeriodes,
            'selectedPeriode' => $selectedPeriode,
        ];
    }

    // Reset Form
    public function resetForm()
    {
        $this->indikatorId = null;
        $this->kode_indikator = '';
        $this->nama_indikator = '';
        $this->kategori = 'IKU';
        $this->isEdit = false;
        $this->resetValidation();
    }

    // Buka Modal
    public function openModal()
    {
        $this->resetForm();
        $this->isOpen = true;
    }

    // Simpan Data (Create & Update)
    public function save()
    {
        $periode = Periode::find($this->selectedPeriodeId);
        if (!$periode || $periode->status === 'closed') {
            session()->flash('error', 'Gagal menyimpan. Periode ini sudah ditutup atau tidak valid.');
            return;
        }

        $validated = $this->validate([
            'kode_indikator' => [
                'required', 
                'string', 
                'max:20', 
                // Unik berdasarkan kombinasi periode_id dan kode_indikator
                Rule::unique('performance_indicators', 'kode_indikator')
                    ->where('periode_id', $this->selectedPeriodeId)
                    ->ignore($this->indikatorId)
            ],
            'nama_indikator' => 'required|string|max:255',
            'kategori' => 'required|in:IKU,IKT',
        ]);

        $validated['periode_id'] = $this->selectedPeriodeId;

        PerformanceIndicator::updateOrCreate(
            ['id' => $this->indikatorId],
            $validated
        );

        $this->isOpen = false;
        session()->flash('message', $this->isEdit ? 'Indikator berhasil diperbarui.' : 'Indikator baru berhasil ditambahkan.');
    }

    // Edit Data
    public function edit($id)
    {
        $this->resetValidation();
        $indicator = PerformanceIndicator::findOrFail($id);
        
        $this->indikatorId = $indicator->id;
        $this->kode_indikator = $indicator->kode_indikator;
        $this->nama_indikator = $indicator->nama_indikator;
        $this->kategori = $indicator->kategori;
        $this->isEdit = true;
        $this->isOpen = true;
    }

    // Hapus Data
    public function delete($id)
    {
        PerformanceIndicator::destroy($id);
        session()->flash('message', 'Indikator berhasil dihapus.');
    }

    // ========================================================
    // FITUR SPESIAL: SALIN INDIKATOR DARI PERIODE SEBELUMNYA
    // ========================================================
    public function copyFromPreviousPeriod()
    {
        if (!$this->selectedPeriodeId) return;

        $currentPeriode = Periode::find($this->selectedPeriodeId);
        
        // Cari periode sebelumnya berdasarkan tanggal mulai yang lebih tua
        $previousPeriode = Periode::where('tanggal_mulai', '<', $currentPeriode->tanggal_mulai)
                                  ->orderBy('tanggal_mulai', 'desc')
                                  ->first();

        if (!$previousPeriode) {
            session()->flash('error', 'Sistem tidak menemukan periode sebelumnya untuk disalin.');
            return;
        }

        $oldIndicators = PerformanceIndicator::where('periode_id', $previousPeriode->id)->get();

        if ($oldIndicators->isEmpty()) {
            session()->flash('error', 'Periode sebelumnya (' . $previousPeriode->nama_periode . ') belum memiliki data indikator.');
            return;
        }

        // Duplikasi data
        foreach ($oldIndicators as $old) {
            PerformanceIndicator::create([
                'periode_id' => $currentPeriode->id,
                'kode_indikator' => $old->kode_indikator,
                'nama_indikator' => $old->nama_indikator,
                'kategori' => $old->kategori,
            ]);
        }

        session()->flash('message', 'Berhasil menyalin ' . $oldIndicators->count() . ' indikator dari periode ' . $previousPeriode->nama_periode . '.');
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Section & Dropdown Periode -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-theme-surface p-5 rounded-3xl border border-theme-border shadow-sm">
            <div class="flex-1">
                <h2 class="text-2xl font-extrabold text-theme-text tracking-tight uppercase">Master Indikator Kinerja</h2>
                <p class="text-sm text-theme-muted mt-1">Kelola daftar IKU dan IKT sebagai acuan target program kerja tahunan.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                <div class="w-full sm:w-64">
                    <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Periode Renstra / Kinerja</label>
                    <select wire:model.live="selectedPeriodeId" class="block w-full border-gray-300 bg-gray-50 rounded-xl text-sm font-bold text-gray-900 focus:ring-primary focus:border-primary shadow-sm cursor-pointer">
                        <option value="">-- Pilih Periode --</option>
                        @foreach($allPeriodes as $p)
                            <option value="{{ $p->id }}">{{ $p->nama_periode }} @if($p->is_current) (Aktif) @endif</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-auto">
                    <label class="block text-[10px] text-transparent hidden sm:block mb-1">-</label>
                    <button wire:click="openModal" 
                            @if(!$selectedPeriode || $selectedPeriode->status === 'closed') disabled @endif 
                            class="inline-flex items-center justify-center w-full px-5 py-2.5 bg-primary hover:bg-primary-hover disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-bold rounded-xl shadow-sm transition-all">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Indikator
                    </button>
                </div>
            </div>
        </div>

        <!-- Feedback Message -->
        @if (session()->has('message'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center justify-between shadow-sm">
                <span class="text-sm font-medium flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ session('message') }}
                </span>
                <button @click="show = false" class="text-emerald-500 hover:text-emerald-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif
        @if (session()->has('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl flex items-center justify-between shadow-sm">
                <span class="text-sm font-medium flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('error') }}
                </span>
                <button @click="show = false" class="text-red-500 hover:text-red-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        @if($selectedPeriode)
            <!-- Info Status Periode & Fitur Salin -->
            @if($indicators->isEmpty() && $selectedPeriode->status !== 'closed')
                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-blue-800">Periode Masih Kosong</h3>
                            <p class="text-xs text-blue-700 mt-1">Gunakan fitur salin untuk mengimpor seluruh indikator dari periode sebelumnya secara otomatis.</p>
                        </div>
                    </div>
                    <button wire:click="copyFromPreviousPeriod" wire:confirm="Salin semua IKU/IKT dari periode sebelumnya ke periode ini?" class="w-full md:w-auto px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-md transition-all whitespace-nowrap">
                        Salin dari Periode Sebelumnya
                    </button>
                </div>
            @endif

            <!-- Filter & Table Card -->
            <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-sm overflow-hidden">
                <div class="p-4 border-b border-theme-border bg-theme-body/30">
                    <div class="relative max-w-sm">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input wire:model.live="search" type="text" placeholder="Cari kode atau nama indikator..." class="block w-full pl-10 pr-3 py-2 border border-theme-border rounded-xl bg-theme-surface text-sm focus:ring-primary focus:border-primary">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-[11px] text-theme-muted uppercase font-bold bg-theme-body border-b border-theme-border tracking-wider">
                            <tr>
                                <th class="px-6 py-4 w-32">Kode</th>
                                <th class="px-6 py-4">Nama Indikator Kinerja</th>
                                <th class="px-6 py-4 w-32 text-center">Kategori</th>
                                <th class="px-6 py-4 w-32 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            @forelse($indicators as $item)
                                <tr class="hover:bg-theme-body/50 transition-colors group">
                                    <td class="px-6 py-4 font-bold text-primary">{{ $item->kode_indikator }}</td>
                                    <td class="px-6 py-4 text-theme-text font-medium">{{ $item->nama_indikator }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $item->kategori === 'IKU' ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-amber-50 text-amber-600 border border-amber-100' }}">
                                            {{ $item->kategori }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        @if($selectedPeriode->status !== 'closed')
                                            <button wire:click="edit({{ $item->id }})" class="p-2 text-theme-muted hover:text-primary transition-colors bg-theme-body rounded-lg hover:bg-primary/10">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                            <button onclick="confirm('Yakin ingin menghapus indikator ini? Pastikan tidak ada Proker yang terikat dengannya.') || event.stopImmediatePropagation()" wire:click="delete({{ $item->id }})" class="p-2 text-theme-muted hover:text-red-500 transition-colors bg-theme-body rounded-lg hover:bg-red-50">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        @else
                                            <span class="text-xs text-theme-muted italic">Terkunci</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-16 text-center text-theme-muted">
                                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-theme-body mb-3">
                                            <svg class="w-8 h-8 text-theme-muted opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                        </div>
                                        <p class="text-sm font-medium">Tidak ada data indikator pada periode ini.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <!-- State jika belum pilih periode sama sekali -->
            <div class="bg-amber-50 border border-amber-200 text-amber-700 rounded-2xl p-6 text-center shadow-sm">
                <svg class="w-12 h-12 text-amber-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <h3 class="text-lg font-bold">Silakan Pilih Periode</h3>
                <p class="text-sm mt-1">Pilih periode kinerja pada dropdown di atas untuk mengelola IKU/IKT.</p>
            </div>
        @endif

        <!-- Modal CRUD (Alpine.js) -->
        <div 
            x-data="{ show: @entangle('isOpen') }" 
            x-show="show" 
            x-cloak
            class="fixed inset-0 z-[60] overflow-y-auto"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
        >
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-theme-text/40 backdrop-blur-sm" @click="show = false"></div>

                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-theme-surface rounded-3xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-theme-border">
                    <form wire:submit.prevent="save">
                        <div class="p-6 space-y-4">
                            <h3 class="text-lg font-bold text-theme-text">
                                {{ $isEdit ? 'Perbarui Indikator' : 'Tambah Indikator Baru' }}
                                <span class="block text-xs font-normal text-theme-muted mt-1">Periode: {{ $selectedPeriode->nama_periode ?? '' }}</span>
                            </h3>
                            
                            <div class="space-y-4 pt-4">
                                <!-- Kode -->
                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Kode Indikator</label>
                                    <input wire:model="kode_indikator" type="text" placeholder="Contoh: IKU-1 atau IKT-01" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30 uppercase">
                                    @error('kode_indikator') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                <!-- Nama -->
                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Nama Indikator / Deskripsi</label>
                                    <textarea wire:model="nama_indikator" rows="3" placeholder="Contoh: Persentase lulusan S1 yang berhasil mendapat pekerjaan..." class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30"></textarea>
                                    @error('nama_indikator') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                <!-- Kategori -->
                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Kategori</label>
                                    <select wire:model="kategori" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30">
                                        <option value="IKU">IKU (Indikator Kinerja Utama)</option>
                                        <option value="IKT">IKT (Indikator Kinerja Tambahan)</option>
                                    </select>
                                    @error('kategori') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="p-6 bg-theme-body/30 flex justify-end gap-3 border-t border-theme-border">
                            <button type="button" @click="show = false" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">
                                Batal
                            </button>
                            <button type="submit" class="px-6 py-2 bg-primary hover:bg-primary-hover text-white text-sm font-bold rounded-xl shadow-lg shadow-primary/20 transition-all">
                                {{ $isEdit ? 'Simpan Perubahan' : 'Tambahkan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>