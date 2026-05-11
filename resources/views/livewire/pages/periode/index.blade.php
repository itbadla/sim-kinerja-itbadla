<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Periode;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    // State Modals
    public $isFormModalOpen = false;
    public $isDeleteModalOpen = false;

    // Form Properties
    public $periodeId;
    public $nama_periode;
    public $tanggal_mulai;
    public $tanggal_selesai;
    public $status = 'planning';
    public $is_current = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->periodeId = null;
        $this->nama_periode = '';
        $this->tanggal_mulai = '';
        $this->tanggal_selesai = '';
        $this->status = 'planning';
        $this->is_current = false;
        $this->resetValidation();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->isFormModalOpen = true;
    }

    public function openEditModal($id)
    {
        $periode = Periode::findOrFail($id);
        $this->periodeId = $periode->id;
        $this->nama_periode = $periode->nama_periode;
        $this->tanggal_mulai = $periode->tanggal_mulai->format('Y-m-d');
        $this->tanggal_selesai = $periode->tanggal_selesai->format('Y-m-d');
        $this->status = $periode->status;
        $this->is_current = $periode->is_current;

        $this->isFormModalOpen = true;
    }

    public function save()
    {
        $validated = $this->validate([
            'nama_periode' => 'required|string|max:50',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'status' => 'required|in:planning,active,closed',
            'is_current' => 'boolean',
        ]);

        DB::transaction(function () use ($validated) {
            // Jika periode ini diset sebagai 'is_current', nonaktifkan periode lain
            if ($this->is_current) {
                Periode::where('is_current', true)->update(['is_current' => false]);
            }

            Periode::updateOrCreate(
                ['id' => $this->periodeId],
                $validated
            );
        });

        $this->isFormModalOpen = false;
        session()->flash('message', 'Data periode berhasil diperbarui.');
    }

    public function confirmDelete($id)
    {
        $this->periodeId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function destroy()
    {
        Periode::findOrFail($this->periodeId)->delete();
        $this->isDeleteModalOpen = false;
        session()->flash('message', 'Periode berhasil dihapus.');
    }

    public function with(): array
    {
        return [
            'periodes' => Periode::where('nama_periode', 'like', '%' . $this->search . '%')
                ->orderBy('tanggal_mulai', 'desc')
                ->paginate(10),
        ];
    }
}; ?>

<div class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-theme-text tracking-tight uppercase">Master Periode Kinerja</h1>
            <p class="text-sm text-theme-muted mt-1">Kelola periode aktif akademik/anggaran, masa perencanaan, dan penutupan buku.</p>
        </div>
        
        <button wire:click="openCreateModal" class="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Periode Baru
        </button>
    </div>

    <!-- Filter & Search -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex items-center">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live="search" class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text" placeholder="Cari nama periode (Cth: TA 2024/2025)...">
        </div>
    </div>

    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center justify-between shadow-sm">
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Table -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-theme-body border-b border-theme-border text-theme-muted uppercase text-[10px] font-bold tracking-widest">
                    <tr>
                        <th class="px-6 py-4">Nama Periode</th>
                        <th class="px-6 py-4">Rentang Tanggal</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Periode Aktif</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($periodes as $periode)
                    <tr wire:key="{{ $periode->id }}" class="hover:bg-theme-body/30 transition-colors">
                        <td class="px-6 py-4 font-bold text-theme-text">{{ $periode->nama_periode }}</td>
                        <td class="px-6 py-4 text-theme-muted text-xs">
                            {{ $periode->tanggal_mulai->format('d M Y') }} s/d {{ $periode->tanggal_selesai->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $statusMap = [
                                    'planning' => ['bg-blue-100 text-blue-700', 'Perencanaan'],
                                    'active' => ['bg-emerald-100 text-emerald-700', 'Berjalan'],
                                    'closed' => ['bg-red-100 text-red-700', 'Ditutup'],
                                ];
                                $style = $statusMap[$periode->status] ?? ['bg-gray-100 text-gray-700', $periode->status];
                            @endphp
                            <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $style[0] }}">
                                {{ $style[1] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center text-xs">
                            @if($periode->is_current)
                                <span class="text-emerald-600 font-bold flex items-center justify-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    AKTIF
                                </span>
                            @else
                                <span class="text-theme-muted opacity-50">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <button wire:click="openEditModal({{ $periode->id }})" class="p-2 text-theme-muted hover:text-primary transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <button wire:click="confirmDelete({{ $periode->id }})" class="p-2 text-theme-muted hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-theme-muted italic">Belum ada data periode kinerja.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-theme-border">
            {{ $periodes->links() }}
        </div>
    </div>

    <!-- Modal Form -->
    @if($isFormModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="fixed inset-0 bg-theme-text/40 backdrop-blur-sm" wire:click="$set('isFormModalOpen', false)"></div>
        <div class="relative bg-theme-surface w-full max-w-lg rounded-2xl border border-theme-border shadow-xl overflow-hidden">
            <form wire:submit.prevent="save">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-theme-text">{{ $periodeId ? 'Edit' : 'Tambah' }} Periode Kinerja</h3>
                    <button type="button" wire:click="$set('isFormModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Nama Periode</label>
                        <input type="text" wire:model="nama_periode" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body" placeholder="Misal: TA 2024/2025">
                        @error('nama_periode') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Tanggal Mulai</label>
                            <input type="date" wire:model="tanggal_mulai" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body">
                            @error('tanggal_mulai') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Tanggal Selesai</label>
                            <input type="date" wire:model="tanggal_selesai" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body">
                            @error('tanggal_selesai') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Status Sistem</label>
                        <select wire:model="status" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body">
                            <option value="planning">PLANNING (Input Proker & Indikator)</option>
                            <option value="active">ACTIVE (Input Logbook & Keuangan)</option>
                            <option value="closed">CLOSED (Terkunci / Arsip)</option>
                        </select>
                        @error('status') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer pt-2">
                        <input type="checkbox" wire:model="is_current" class="rounded text-primary focus:ring-primary border-theme-border">
                        <span class="text-xs font-bold text-theme-text uppercase">Jadikan Periode Aktif Sistem (Default)</span>
                    </label>
                </div>
                <div class="px-6 py-4 bg-theme-body/30 border-t border-theme-border flex justify-end gap-3">
                    <button type="button" wire:click="$set('isFormModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-2 rounded-xl font-bold text-sm shadow-sm transition-all">Simpan Periode</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Modal Delete -->
    @if($isDeleteModalOpen)
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-theme-text/40 backdrop-blur-sm" wire:click="$set('isDeleteModalOpen', false)"></div>
        <div class="relative bg-theme-surface w-full max-w-sm rounded-2xl border border-theme-border shadow-xl p-6 text-center">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 font-bold">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-theme-text">Hapus Periode?</h3>
            <p class="text-sm text-theme-muted mt-2 mb-6">Semua logbook dan program kerja yang terikat dengan periode ini akan kehilangan referensinya atau ikut terhapus.</p>
            <div class="flex gap-3">
                <button wire:click="$set('isDeleteModalOpen', false)" class="flex-1 py-2.5 text-sm font-bold text-theme-muted bg-theme-body rounded-xl border border-theme-border hover:bg-theme-surface transition-colors">Batal</button>
                <button wire:click="destroy" class="flex-1 py-2.5 text-sm font-bold text-white bg-red-500 rounded-xl hover:bg-red-600 transition-colors shadow-sm">Ya, Hapus</button>
            </div>
        </div>
    </div>
    @endif
</div>