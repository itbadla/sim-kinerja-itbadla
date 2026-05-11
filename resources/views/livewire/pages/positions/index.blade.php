<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Position;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    public $search = '';

    // State Modals
    public $isCreateModalOpen = false;
    public $isEditModalOpen = false;
    public $isDeleteModalOpen = false;

    // Form Properties
    public $positionId;
    public $nama_jabatan;
    public $level_otoritas = 5;
    public $kategori = 'Struktural';

    // Options untuk kategori
    public array $kategoriOptions = ['Pimpinan', 'Struktural', 'Akademik', 'Administratif'];

    public function resetForm()
    {
        $this->positionId = null;
        $this->nama_jabatan = '';
        $this->level_otoritas = 5;
        $this->kategori = 'Struktural';
        $this->resetValidation();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->isCreateModalOpen = true;
    }

    public function openEditModal($id)
    {
        $this->resetValidation();
        $position = Position::findOrFail($id);

        $this->positionId = $position->id;
        $this->nama_jabatan = $position->nama_jabatan;
        $this->level_otoritas = $position->level_otoritas;
        $this->kategori = $position->kategori;

        $this->isEditModalOpen = true;
    }

    public function store()
    {
        $validated = $this->validate([
            'nama_jabatan' => 'required|string|max:255|unique:positions,nama_jabatan',
            'level_otoritas' => 'required|integer|min:1|max:10',
            'kategori' => 'required|string|in:Pimpinan,Struktural,Akademik,Administratif',
        ]);

        DB::transaction(function () use ($validated) {
            // 1. Buat atau cari Role di tabel Spatie
            $role = Role::firstOrCreate(['name' => $validated['nama_jabatan']]);

            // 2. Buat Jabatan Baru dan simpan role_id-nya
            $validated['role_id'] = $role->id;
            Position::create($validated);
        });

        $this->isCreateModalOpen = false;
        session()->flash('message', 'Jabatan berhasil ditambahkan dan Role otomatis dibuat.');
    }

    public function update()
    {
        $validated = $this->validate([
            'nama_jabatan' => ['required', 'string', 'max:255', Rule::unique('positions', 'nama_jabatan')->ignore($this->positionId)],
            'level_otoritas' => 'required|integer|min:1|max:10',
            'kategori' => 'required|string|in:Pimpinan,Struktural,Akademik,Administratif',
        ]);

        DB::transaction(function () use ($validated) {
            $position = Position::findOrFail($this->positionId);
            $oldName = $position->nama_jabatan;
            
            // 1. [SINKRONISASI] Cek dan Update Role Spatie
            if ($position->role_id) {
                $role = Role::find($position->role_id);
                if ($role) {
                    if ($role->name !== 'Super Admin' && $oldName !== $validated['nama_jabatan']) {
                        $role->update(['name' => $validated['nama_jabatan']]);
                    }
                } else {
                    // Fallback: Jika role di Spatie tidak sengaja terhapus manual, buat ulang
                    $role = Role::firstOrCreate(['name' => $validated['nama_jabatan']]);
                    $validated['role_id'] = $role->id;
                }
            } else {
                // Jika jabatan lama belum punya role_id, buat dan tautkan sekarang
                $role = Role::firstOrCreate(['name' => $validated['nama_jabatan']]);
                $validated['role_id'] = $role->id;
            }

            // 2. Update Jabatan
            $position->update($validated);
        });

        $this->isEditModalOpen = false;
        session()->flash('message', 'Data jabatan & Role berhasil diperbarui.');
    }

    public function confirmDelete($id)
    {
        $this->positionId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function destroy()
    {
        $position = Position::findOrFail($this->positionId);
        $roleId = $position->role_id;

        // Cek apakah jabatan ini masih digunakan di unit_user
        $usedCount = DB::table('unit_user')
            ->where('position_id', $this->positionId)
            ->count();

        if ($usedCount > 0) {
            session()->flash('error', "Jabatan \"{$position->nama_jabatan}\" masih digunakan oleh {$usedCount} pengguna. Lepas penempatan mereka terlebih dahulu.");
            $this->isDeleteModalOpen = false;
            return;
        }

        DB::transaction(function () use ($position, $roleId) {
            // 1. Hapus Jabatan
            $position->delete();

            // 2. [SINKRONISASI] Hapus Role-nya BILA tidak ada user yang memakainya
            if ($roleId) {
                $role = Role::find($roleId);
                if ($role && $role->name !== 'Super Admin') {
                    $usersWithRole = DB::table('model_has_roles')->where('role_id', $role->id)->count();
                    if ($usersWithRole === 0) {
                        $role->delete();
                    }
                }
            }
        });

        $this->isDeleteModalOpen = false;
        session()->flash('message', 'Jabatan berhasil dihapus dan Role dibersihkan.');
    }

    // Fungsi pintar untuk mensinkronkan semua jabatan lama secara otomatis
    public function syncAllRoles()
    {
        $unsyncedPositions = Position::whereNull('role_id')->get();
        $count = 0;

        DB::transaction(function () use ($unsyncedPositions, &$count) {
            foreach ($unsyncedPositions as $position) {
                $role = Role::firstOrCreate(['name' => $position->nama_jabatan]);
                $position->update(['role_id' => $role->id]);
                $count++;
            }
        });

        if ($count > 0) {
            session()->flash('message', "Berhasil mensinkronkan {$count} jabatan dengan Peran (Spatie Roles).");
        }
    }

    public function closeModal()
    {
        $this->isCreateModalOpen = false;
        $this->isEditModalOpen = false;
        $this->isDeleteModalOpen = false;
        $this->resetForm();
    }

    public function with(): array
    {
        return [
            'positions' => Position::when($this->search, function ($q) {
                    $q->where('nama_jabatan', 'like', '%' . $this->search . '%')
                      ->orWhere('kategori', 'like', '%' . $this->search . '%');
                })
                ->orderBy('level_otoritas')
                ->orderBy('nama_jabatan')
                ->get(),
            'unsyncedCount' => Position::whereNull('role_id')->count(), // Menghitung yang belum sinkron
        ];
    }
}; ?>

<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Master Jabatan</h1>
            <p class="text-sm text-theme-muted mt-1">Kelola kamus jabatan baku dan level otoritas hierarki kampus.</p>
        </div>
        <button wire:click="openCreateModal" class="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Jabatan
        </button>
    </div>

    {{-- ALERT CERDAS: Tombol Sinkronisasi Cepat jika ada data yang belum terikat --}}
    @if($unsyncedCount > 0)
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm animate-pulse-slow">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center text-amber-600 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold">Sinkronisasi Diperlukan</h4>
                    <p class="text-xs mt-0.5 opacity-90">Ada <span class="font-bold">{{ $unsyncedCount }} jabatan lama</span> yang belum terhubung dengan tabel Peran (Spatie Roles).</p>
                </div>
            </div>
            <button wire:click="syncAllRoles" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow-sm transition-all whitespace-nowrap">
                Sinkronkan Sekarang
            </button>
        </div>
    @endif

    {{-- Feedback Standard --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" x-transition class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center justify-between shadow-sm">
            <span class="text-sm font-medium">{{ session('message') }}</span>
            <button @click="show = false" class="text-emerald-500 hover:text-emerald-700"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl flex items-center justify-between shadow-sm">
            <span class="text-sm font-medium">{{ session('error') }}</span>
            <button @click="show = false" class="text-red-500 hover:text-red-700"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
    @endif

    {{-- Search --}}
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex items-center gap-3">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live="search" class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary text-sm text-theme-text transition-all" placeholder="Cari nama jabatan atau kategori...">
        </div>
    </div>

    {{-- Legend Level Otoritas --}}
    <div class="bg-theme-surface border border-theme-border rounded-2xl p-5 shadow-sm">
        <p class="text-[10px] font-bold text-theme-muted uppercase tracking-[0.2em] mb-3">Panduan Level Otoritas</p>
        <div class="flex flex-wrap gap-2">
            @php
                $levelColors = [
                    1 => ['bg' => 'bg-red-50 border-red-200 text-red-700', 'label' => 'Lv.1 — Rektor'],
                    2 => ['bg' => 'bg-orange-50 border-orange-200 text-orange-700', 'label' => 'Lv.2 — Wakil Rektor'],
                    3 => ['bg' => 'bg-amber-50 border-amber-200 text-amber-700', 'label' => 'Lv.3 — Dekan / Kepala'],
                    4 => ['bg' => 'bg-blue-50 border-blue-200 text-blue-700', 'label' => 'Lv.4 — Kaprodi'],
                    5 => ['bg' => 'bg-emerald-50 border-emerald-200 text-emerald-700', 'label' => 'Lv.5 — Dosen'],
                    6 => ['bg' => 'bg-slate-100 border-slate-200 text-slate-600', 'label' => 'Lv.6 — Staff'],
                ];
            @endphp
            @foreach($levelColors as $lv => $lc)
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold border {{ $lc['bg'] }}">
                    {{ $lc['label'] }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-16">Level</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Nama Jabatan</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-40">Kategori</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($positions as $position)
                        @php
                            $levelBadge = match(true) {
                                $position->level_otoritas <= 1 => 'bg-red-500 text-white',
                                $position->level_otoritas <= 2 => 'bg-orange-500 text-white',
                                $position->level_otoritas <= 3 => 'bg-amber-500 text-white',
                                $position->level_otoritas <= 4 => 'bg-blue-500 text-white',
                                $position->level_otoritas <= 5 => 'bg-emerald-500 text-white',
                                default => 'bg-slate-400 text-white',
                            };
                            $kategoriBadge = match($position->kategori) {
                                'Pimpinan' => 'bg-red-50 text-red-600 border-red-100',
                                'Struktural' => 'bg-blue-50 text-blue-600 border-blue-100',
                                'Akademik' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                'Administratif' => 'bg-slate-100 text-slate-600 border-slate-200',
                                default => 'bg-theme-body text-theme-muted border-theme-border',
                            };
                        @endphp
                        <tr wire:key="pos-{{ $position->id }}" class="hover:bg-theme-body/30 transition-colors group">
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-sm font-black {{ $levelBadge }} shadow-sm">
                                    {{ $position->level_otoritas }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <span class="text-sm font-bold text-theme-text">{{ $position->nama_jabatan }}</span>
                                    
                                    {{-- Lencana Peringatan jika belum punya Role ID --}}
                                    @if(!$position->role_id)
                                        <span class="ml-3 px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[10px] font-bold border border-amber-200" title="Klik tombol Sinkronisasi di atas">
                                            Belum Sinkron
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase border {{ $kategoriBadge }}">
                                    {{ $position->kategori }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1 opacity-40 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="openEditModal({{ $position->id }})" class="text-theme-muted hover:text-primary p-2 rounded-lg hover:bg-theme-body transition-colors" title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button wire:click="confirmDelete({{ $position->id }})" class="text-theme-muted hover:text-red-500 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Hapus">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-theme-muted italic">
                                Tidak ada data jabatan ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL CREATE --}}
    @if($isCreateModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-lg my-auto">
                <div class="px-6 py-4 border-b border-theme-border flex justify-between items-center bg-theme-body/50 rounded-t-2xl">
                    <h3 class="text-lg font-bold text-theme-text">Tambah Jabatan Baru</h3>
                    <button wire:click="closeModal" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form wire:submit.prevent="store" class="p-6 space-y-5">
                    <div>
                        <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Nama Jabatan</label>
                        <input type="text" wire:model="nama_jabatan" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30" placeholder="Contoh: Dekan, Kaprodi, Dosen">
                        @error('nama_jabatan') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Level Otoritas</label>
                            <input type="number" wire:model="level_otoritas" min="1" max="10" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            <p class="text-[9px] text-theme-muted mt-1">Semakin kecil = semakin tinggi</p>
                            @error('level_otoritas') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Kategori</label>
                            <select wire:model="kategori" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                                @foreach($kategoriOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('kategori') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-theme-border">
                        <button type="button" wire:click="closeModal" class="text-sm font-bold text-theme-muted hover:text-theme-text">Batal</button>
                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-xl font-bold text-sm shadow-lg shadow-primary/20">Simpan Jabatan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL EDIT --}}
    @if($isEditModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-lg my-auto">
                <div class="px-6 py-4 border-b border-theme-border flex justify-between items-center bg-theme-body/50 rounded-t-2xl">
                    <h3 class="text-lg font-bold text-theme-text">Edit Data Jabatan</h3>
                    <button wire:click="closeModal" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form wire:submit.prevent="update" class="p-6 space-y-5">
                    <div>
                        <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Nama Jabatan</label>
                        <input type="text" wire:model="nama_jabatan" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                        @error('nama_jabatan') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Level Otoritas</label>
                            <input type="number" wire:model="level_otoritas" min="1" max="10" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            <p class="text-[9px] text-theme-muted mt-1">Semakin kecil = semakin tinggi</p>
                            @error('level_otoritas') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Kategori</label>
                            <select wire:model="kategori" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                                @foreach($kategoriOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('kategori') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-theme-border">
                        <button type="button" wire:click="closeModal" class="text-sm font-bold text-theme-muted hover:text-theme-text">Batal</button>
                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-xl font-bold text-sm shadow-lg shadow-primary/20">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL DELETE --}}
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[110] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4">
            <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-2xl w-full max-w-sm p-6 text-center">
                <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-theme-text mb-2">Hapus Jabatan?</h3>
                <p class="text-sm text-theme-muted mb-6">Data jabatan ini akan dihapus permanen. Pastikan tidak ada user yang menggunakan jabatan ini.</p>
                <div class="flex gap-3">
                    <button wire:click="closeModal" class="flex-1 py-2 text-sm font-bold text-theme-muted bg-theme-body rounded-xl border border-theme-border">Batal</button>
                    <button wire:click="destroy" class="flex-1 py-2 text-sm font-bold text-white bg-red-500 rounded-xl shadow-md">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>