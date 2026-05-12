<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Unit;
use App\Models\User;
use App\Models\Position;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    // State Modals
    public $isCreateModalOpen = false;
    public $isEditModalOpen = false;
    public $isDeleteModalOpen = false;

    // Form Properties (Unit)
    public $unitId;
    public $kode_unit;
    public $nama_unit;
    public $parent_id;
    public $kepala_unit_id;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->unitId = null;
        $this->kode_unit = '';
        $this->nama_unit = '';
        $this->parent_id = '';
        $this->kepala_unit_id = '';
        $this->resetValidation();
    }

    // --- UNIT CONTROLS ---

    public function openCreateModal($parentId = null)
    {
        $this->resetForm();
        if ($parentId) {
            $this->parent_id = $parentId;
        }
        $this->isCreateModalOpen = true;
    }

    public function openEditModal($id)
    {
        $this->resetValidation();
        $unit = Unit::findOrFail($id);
        
        $this->unitId = $unit->id;
        $this->kode_unit = $unit->kode_unit;
        $this->nama_unit = $unit->nama_unit;
        $this->parent_id = $unit->parent_id;
        $this->kepala_unit_id = $unit->kepala_unit_id;

        $this->isEditModalOpen = true;
    }

    public function store()
    {
        $validated = $this->validate([
            'kode_unit' => 'required|string|max:20|unique:units,kode_unit',
            'nama_unit' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:units,id',
            'kepala_unit_id' => 'nullable|exists:users,id',
        ]);

        Unit::create($validated);

        $this->isCreateModalOpen = false;
        session()->flash('message', 'Unit berhasil ditambahkan.');
    }

    public function update()
    {
        $validated = $this->validate([
            'kode_unit' => ['required', 'string', 'max:20', Rule::unique('units', 'kode_unit')->ignore($this->unitId)],
            'nama_unit' => 'required|string|max:255',
            'parent_id' => [
                'nullable', 
                'exists:units,id',
                fn ($attr, $value, $fail) => $value == $this->unitId ? $fail('Unit tidak boleh menjadi induk bagi dirinya sendiri.') : null,
            ],
            'kepala_unit_id' => 'nullable|exists:users,id',
        ]);

        $unit = Unit::findOrFail($this->unitId);
        $unit->update($validated);

        $this->isEditModalOpen = false;
        session()->flash('message', 'Data unit diperbarui.');
    }

    public function confirmDelete($id)
    {
        $this->unitId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function destroy()
    {
        Unit::findOrFail($this->unitId)->delete();
        $this->isDeleteModalOpen = false;
        session()->flash('message', 'Unit dihapus.');
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
        $query = Unit::withCount('users')->with([
            'kepalaUnit', 
            'children' => fn($q) => $q->withCount('users'),
            'children.kepalaUnit', 
            'children.children' => fn($q) => $q->withCount('users'),
            'children.children.kepalaUnit',
            // Relasi Tingkat 4
            'children.children.children' => fn($q) => $q->withCount('users'),
            'children.children.children.kepalaUnit',
            // Relasi Tingkat 5
            'children.children.children.children' => fn($q) => $q->withCount('users'),
            'children.children.children.children.kepalaUnit',
        ]);

        if ($this->search) {
            $units = $query->where('nama_unit', 'like', '%' . $this->search . '%')
                           ->orWhere('kode_unit', 'like', '%' . $this->search . '%')
                           ->get();
        } else {
            $units = $query->whereNull('parent_id')->orderBy('nama_unit')->get();
        }

        return [
            'units' => $units,
            'availableParents' => Unit::when($this->unitId, fn($q) => $q->where('id', '!=', $this->unitId))->orderBy('nama_unit')->get(),
            'availableUsers' => User::orderBy('name')->get(),
            'availablePositions' => Position::orderBy('level_otoritas')->get(),
            'positionsMap' => Position::pluck('nama_jabatan', 'id'),
        ];
    }
}; ?>

<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Kelola Unit Kerja</h1>
            <p class="text-sm text-theme-muted mt-1">Manajemen hierarki struktur organisasi dan penempatan personil.</p>
        </div>
        
        <button wire:click="openCreateModal()" class="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm shadow-primary/20 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Unit Baru
        </button>
    </div>

    <!-- Feedback Message -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center justify-between shadow-sm">
            <span class="text-sm font-medium">{{ session('message') }}</span>
            <button @click="show = false" class="text-emerald-500 hover:text-emerald-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    <!-- Kotak Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex items-center gap-3">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live="search" class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body/50 rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all" placeholder="Cari nama atau kode unit...">
        </div>
    </div>

    <!-- Tree View (Daftar Unit) -->
    <div class="space-y-4">
        @forelse($units as $unit)
            <!-- Root Unit (Level 1) -->
            <div wire:key="root-{{ $unit->id }}" class="bg-theme-surface border border-theme-border rounded-2xl p-5 shadow-sm hover:border-primary/30 transition-colors">
                
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-lg shadow-inner">
                            {{ substr($unit->kode_unit, 0, 1) }}
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-theme-text uppercase">{{ $unit->nama_unit }}</h3>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <span class="text-xs font-semibold text-primary px-2 py-0.5 bg-primary/10 rounded-md border border-primary/20">{{ $unit->kode_unit }}</span>
                                <span class="text-xs text-theme-muted">Pimpinan: <span class="font-semibold text-theme-text">{{ $unit->kepalaUnit->name ?? 'Belum Ditugaskan' }}</span></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 sm:self-center">
                        <!-- TOMBOL DETAIL (GANTI STAF) -->
                        <a href="{{ route('admin.units.show', $unit->id) }}" wire:navigate class="px-3 py-1.5 bg-theme-body border border-theme-border rounded-lg text-xs font-bold text-theme-text hover:text-primary transition-colors flex items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Detail & {{ $unit->users_count }} Staf
                        </a>
                        <div class="w-px h-6 bg-theme-border mx-1"></div>
                        <button wire:click="openCreateModal({{ $unit->id }})" class="p-2 text-theme-muted hover:text-primary transition-colors" title="Tambah Sub-Unit"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></button>
                        <button wire:click="openEditModal({{ $unit->id }})" class="p-2 text-theme-muted hover:text-primary transition-colors" title="Edit Unit"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                        <button wire:click="confirmDelete({{ $unit->id }})" class="p-2 text-theme-muted hover:text-red-500 transition-colors" title="Hapus Unit"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                    </div>
                </div>

                <!-- Anak Unit (Level 2 & Seterusnya) -->
                @if($unit->children->count() > 0 && !$search)
                    <div class="mt-4 pl-4 md:pl-12 space-y-3 border-l-2 border-theme-border/50">
                        @foreach($unit->children as $child)
                            <div wire:key="child-{{ $child->id }}" class="bg-theme-body/30 border border-theme-border rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 group hover:bg-theme-body/50 transition-colors">
                                <div>
                                    <h4 class="text-sm font-bold text-theme-text uppercase">{{ $child->nama_unit }}</h4>
                                    <p class="text-xs text-theme-muted mt-0.5">{{ $child->kode_unit }} • Pimpinan: <span class="font-medium text-theme-text">{{ $child->kepalaUnit->name ?? 'Belum' }}</span></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.units.show', $child->id) }}" wire:navigate class="px-2 py-1 bg-theme-surface border border-theme-border rounded text-xs font-semibold text-theme-muted hover:text-primary transition-colors">
                                        {{ $child->users_count }} Staf
                                    </a>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1">
                                        <button wire:click="openCreateModal({{ $child->id }})" class="p-1.5 text-theme-muted hover:text-primary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></button>
                                        <button wire:click="openEditModal({{ $child->id }})" class="p-1.5 text-theme-muted hover:text-primary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                        <button wire:click="confirmDelete({{ $child->id }})" class="p-1.5 text-theme-muted hover:text-red-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Nested Anak (Level 3) --}}
                            @if($child->children->count() > 0)
                                <div class="pl-4 md:pl-8 space-y-2 border-l-2 border-theme-border/50">
                                    @foreach($child->children as $gc)
                                        <div wire:key="gc-{{ $gc->id }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 bg-theme-surface rounded-lg border border-theme-border/50 group/mini hover:bg-theme-body/30">
                                            <div class="flex items-center gap-2">
                                                <div class="w-1.5 h-1.5 rounded-full bg-primary/40 group-hover/mini:bg-primary transition-colors"></div>
                                                <div>
                                                    <h5 class="text-xs font-bold text-theme-text uppercase">{{ $gc->nama_unit }}</h5>
                                                    <p class="text-[10px] text-theme-muted">{{ $gc->kode_unit }} • Pimpinan: {{ $gc->kepalaUnit->name ?? '-' }}</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('admin.units.show', $gc->id) }}" wire:navigate class="text-[10px] font-semibold text-primary hover:underline">{{ $gc->users_count }} Staf</a>
                                                <div class="opacity-0 group-hover/mini:opacity-100 transition-opacity flex items-center">
                                                    <button wire:click="openCreateModal({{ $gc->id }})" class="p-1 text-theme-muted hover:text-primary"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></button>
                                                    <button wire:click="openEditModal({{ $gc->id }})" class="p-1 text-theme-muted hover:text-primary"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                                    <button wire:click="confirmDelete({{ $gc->id }})" class="p-1 text-theme-muted hover:text-red-500"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Nested Cicit (Level 4) --}}
                                        @if($gc->children->count() > 0)
                                            <div class="pl-4 md:pl-8 mt-1 space-y-2 border-l-2 border-theme-border/40">
                                                @foreach($gc->children as $ggc)
                                                    <div wire:key="ggc-{{ $ggc->id }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2.5 bg-theme-surface/70 rounded-lg border border-theme-border/40 group/micro hover:bg-theme-body/30">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-1 h-1 rounded-full bg-primary/30 group-hover/micro:bg-primary transition-colors"></div>
                                                            <div>
                                                                <h6 class="text-[11px] font-bold text-theme-text uppercase">{{ $ggc->nama_unit }}</h6>
                                                                <p class="text-[9px] text-theme-muted">{{ $ggc->kode_unit }} • Pimpinan: {{ $ggc->kepalaUnit->name ?? '-' }}</p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <a href="{{ route('admin.units.show', $ggc->id) }}" wire:navigate class="text-[9px] font-semibold text-primary hover:underline">{{ $ggc->users_count }} Staf</a>
                                                            <div class="opacity-0 group-hover/micro:opacity-100 transition-opacity flex items-center">
                                                                <button wire:click="openCreateModal({{ $ggc->id }})" class="p-1 text-theme-muted hover:text-primary"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></button>
                                                                <button wire:click="openEditModal({{ $ggc->id }})" class="p-1 text-theme-muted hover:text-primary"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                                                <button wire:click="confirmDelete({{ $ggc->id }})" class="p-1 text-theme-muted hover:text-red-500"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Nested Buyut (Level 5) --}}
                                                    @if($ggc->children->count() > 0)
                                                        <div class="pl-4 md:pl-8 mt-1 space-y-1.5 border-l-2 border-theme-border/30">
                                                            @foreach($ggc->children as $gggc)
                                                                <div wire:key="gggc-{{ $gggc->id }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2 bg-theme-surface/50 rounded-lg border border-theme-border/30 group/nano hover:bg-theme-body/20">
                                                                    <div class="flex items-center gap-2">
                                                                        <div class="w-0.5 h-0.5 rounded-full bg-primary/20 group-hover/nano:bg-primary transition-colors"></div>
                                                                        <div>
                                                                            <h6 class="text-[10px] font-bold text-theme-text uppercase">{{ $gggc->nama_unit }}</h6>
                                                                            <p class="text-[8px] text-theme-muted">{{ $gggc->kode_unit }}</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex items-center gap-1.5">
                                                                        <a href="{{ route('admin.units.show', $gggc->id) }}" wire:navigate class="text-[8px] font-semibold text-primary hover:underline">{{ $gggc->users_count }} Staf</a>
                                                                        <div class="opacity-0 group-hover/nano:opacity-100 transition-opacity flex items-center">
                                                                            <button wire:click="openEditModal({{ $gggc->id }})" class="p-0.5 text-theme-muted hover:text-primary"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                                                                            <button wire:click="confirmDelete({{ $gggc->id }})" class="p-0.5 text-theme-muted hover:text-red-500"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                @endforeach
                                            </div>
                                        @endif

                                    @endforeach
                                </div>
                            @endif

                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="py-16 text-center bg-theme-surface rounded-3xl border border-dashed border-theme-border text-theme-muted text-sm italic">
                Belum ada data unit kerja.
            </div>
        @endforelse
    </div>

    <!-- MODAL FORM UNIT (CREATE/EDIT) -->
    @if($isCreateModalOpen || $isEditModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-y-auto" x-data>
            <div class="fixed inset-0 bg-theme-text/40 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>
            
            <div class="relative bg-theme-surface w-full max-w-2xl rounded-2xl border border-theme-border shadow-xl overflow-hidden my-auto">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-theme-text">{{ $isEditModalOpen ? 'Ubah Data Unit' : 'Tambah Unit Baru' }}</h3>
                    <button type="button" wire:click="closeModal" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="{{ $isEditModalOpen ? 'update' : 'store' }}" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="col-span-1">
                            <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Kode Unit</label>
                            <input type="text" wire:model="kode_unit" placeholder="Misal: TI" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30 uppercase">
                            @error('kode_unit') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Nama Lengkap Unit</label>
                            <input type="text" wire:model="nama_unit" placeholder="Misal: Program Studi Teknik Informatika" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30">
                            @error('nama_unit') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Unit Induk (Parent)</label>
                            <select wire:model="parent_id" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30">
                                <option value="">-- Jadikan Sebagai Unit Root --</option>
                                @foreach($availableParents as $ap)
                                    <option value="{{ $ap->id }}">{{ $ap->nama_unit }} [{{ $ap->kode_unit }}]</option>
                                @endforeach
                            </select>
                            @error('parent_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-theme-muted uppercase mb-1">Ketua / Pimpinan</label>
                            <select wire:model="kepala_unit_id" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary focus:border-primary bg-theme-body/30">
                                <option value="">-- Belum Ditugaskan --</option>
                                @foreach($availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('kepala_unit_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-theme-border">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-2 rounded-xl font-bold text-sm shadow-sm shadow-primary/20 transition-all">Simpan Unit</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- DELETE MODAL -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[150] flex items-center justify-center p-4" x-data>
            <div class="fixed inset-0 bg-theme-text/40 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>
            <div class="relative bg-theme-surface rounded-2xl border border-theme-border shadow-xl w-full max-w-sm p-6 text-center my-auto">
                <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-theme-text mb-2">Hapus Unit Kerja?</h3>
                <p class="text-sm text-theme-muted mb-6">Tindakan ini tidak dapat dibatalkan. Semua penempatan staf di dalam unit ini akan ikut terhapus dari sistem.</p>
                <div class="flex gap-3">
                    <button type="button" wire:click="closeModal" class="flex-1 py-2 text-sm font-bold text-theme-muted bg-theme-body rounded-xl border border-theme-border hover:bg-theme-surface transition-colors">Batal</button>
                    <button type="button" wire:click="destroy" class="flex-1 py-2 text-sm font-bold text-white bg-red-500 rounded-xl shadow-sm hover:bg-red-600 transition-colors">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>