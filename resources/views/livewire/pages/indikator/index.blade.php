<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\PerformanceIndicator;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] class extends Component {
    // State untuk Form
    public $search = '';
    public $isOpen = false;
    public $isEdit = false;
    
    // Properties Model
    public $indikatorId;
    public $kode_indikator;
    public $nama_indikator;
    public $kategori = 'IKU';

    // Ambil data untuk tabel
    public function with(): array
    {
        return [
            'indicators' => PerformanceIndicator::where('nama_indikator', 'like', '%' . $this->search . '%')
                ->orWhere('kode_indikator', 'like', '%' . $this->search . '%')
                ->latest()
                ->get(),
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
        $validated = $this->validate([
            'kode_indikator' => [
                'required', 
                'string', 
                'max:20', 
                Rule::unique('performance_indicators', 'kode_indikator')->ignore($this->indikatorId)
            ],
            'nama_indikator' => 'required|string|max:255',
            'kategori' => 'required|in:IKU,IKT',
        ]);

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
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-theme-text tracking-tight">Master Indikator Kinerja</h2>
                <p class="text-sm text-theme-muted mt-1">Kelola daftar IKU dan IKT sebagai acuan target program kerja.</p>
            </div>
            
            <button wire:click="openModal" class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary-hover text-white text-sm font-bold rounded-xl shadow-sm shadow-primary/20 transition-all">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Indikator
            </button>
        </div>

        <!-- Feedback Message -->
        @if (session()->has('message'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center justify-between">
                <span class="text-sm font-medium">{{ session('message') }}</span>
                <button @click="show = false" class="text-emerald-500 hover:text-emerald-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <!-- Filter & Table Card -->
        <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-sm overflow-hidden">
            <div class="p-6 border-b border-theme-border bg-theme-body/30">
                <div class="relative max-w-sm">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input wire:model.live="search" type="text" placeholder="Cari kode atau nama indikator..." class="block w-full pl-10 pr-3 py-2 border border-theme-border rounded-xl bg-theme-surface text-sm focus:ring-primary focus:border-primary">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-[11px] text-theme-muted uppercase font-bold bg-theme-body border-b border-theme-border">
                        <tr>
                            <th class="px-6 py-4 w-24">Kode</th>
                            <th class="px-6 py-4">Nama Indikator Kinerja</th>
                            <th class="px-6 py-4 w-32">Kategori</th>
                            <th class="px-6 py-4 w-32 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        @forelse($indicators as $item)
                            <tr class="hover:bg-theme-body/50 transition-colors group">
                                <td class="px-6 py-4 font-bold text-primary">{{ $item->kode_indikator }}</td>
                                <td class="px-6 py-4 text-theme-text font-medium">{{ $item->nama_indikator }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $item->kategori === 'IKU' ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-amber-50 text-amber-600 border border-amber-100' }}">
                                        {{ $item->kategori }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button wire:click="edit({{ $item->id }})" class="p-2 text-theme-muted hover:text-primary transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button onclick="confirm('Yakin ingin menghapus indikator ini?') || event.stopImmediatePropagation()" wire:click="delete({{ $item->id }})" class="p-2 text-theme-muted hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-theme-muted italic">Tidak ada data indikator ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

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
                            </h3>
                            
                            <div class="space-y-4 pt-4">
                                <!-- Kode -->
                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Kode Indikator</label>
                                    <input wire:model="kode_indikator" type="text" placeholder="Contoh: IKU-1 atau IKT-01" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30 uppercase">
                                    @error('kode_indikator') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                                </div>

                                <!-- Nama -->
                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Nama Indikator</label>
                                    <textarea wire:model="nama_indikator" rows="3" placeholder="Deskripsi lengkap indikator kinerja..." class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30"></textarea>
                                    @error('nama_indikator') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                                </div>

                                <!-- Kategori -->
                                <div>
                                    <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Kategori</label>
                                    <select wire:model="kategori" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30">
                                        <option value="IKU">IKU (Indikator Kinerja Utama)</option>
                                        <option value="IKT">IKT (Indikator Kinerja Tambahan)</option>
                                    </select>
                                    @error('kategori') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
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