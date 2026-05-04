<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Unit;
use App\Models\User;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $searchStaff = '';

    // ==========================================
    // STATE: MODAL FORM (CREATE & EDIT)
    // ==========================================
    public $isModalOpen = false;
    public $unitId = null;
    public $kode_unit = '';
    public $nama_unit = '';
    public $parent_id = '';
    public $kepala_unit_id = '';
    public $staffIds = []; // Untuk menampung ID staff yang dipilih

    // ==========================================
    // STATE: MODAL HAPUS (DELETE)
    // ==========================================
    public $isDeleteModalOpen = false;
    public ?int $unitToDeleteId = null;

    // Reset pagination saat pencarian berubah
    public function updatingSearch()
    {
        $this->resetPage();
    }

    // ==========================================
    // FUNGSI: FORM (BUKA MODAL CREATE / EDIT)
    // ==========================================
    public function openModal($id = null)
    {
        $this->resetValidation();
        
        if ($id) {
            // Mode Edit
            $unit = Unit::findOrFail($id);
            $this->unitId = $id;
            $this->kode_unit = $unit->kode_unit;
            $this->nama_unit = $unit->nama_unit;
            $this->parent_id = $unit->parent_id;
            $this->kepala_unit_id = $unit->kepala_unit_id;
            // --- TAMBAHKAN BARIS INI ---
            // Ambil ID semua user yang tergabung di unit ini melalui relasi members
            $this->staffIds = $unit->members()->pluck('users.id')->map(fn($id) => (string)$id)->toArray();
            // ---------------------------
        } else {
            // Mode Tambah Baru
            $this->reset(['unitId', 'kode_unit', 'nama_unit', 'parent_id', 'kepala_unit_id']);
        }
        
        $this->isModalOpen = true;
    }

    public function saveUnit()
    {
        $this->validate([
            'kode_unit' => 'required|string|max:20|unique:units,kode_unit,' . $this->unitId,
            'nama_unit' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:units,id',
            'kepala_unit_id' => 'nullable|exists:users,id',
            'staffIds' => 'nullable|array', // Validasi staffIds
        ]);

        // Simpan data unit
        $unit = Unit::updateOrCreate(
            ['id' => $this->unitId],
            [
                'kode_unit' => strtoupper($this->kode_unit),
                'nama_unit' => $this->nama_unit,
                'parent_id' => $this->parent_id ?: null,
                'kepala_unit_id' => $this->kepala_unit_id ?: null,
            ]
        );

        $unit->members()->sync($this->staffIds);

        $this->isModalOpen = false;
    }

    // ==========================================
    // FUNGSI: DELETE (HAPUS UNIT)
    // ==========================================
    public function confirmDelete($id)
    {
        $this->unitToDeleteId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function deleteUnit()
    {
        if ($this->unitToDeleteId) {
            Unit::findOrFail($this->unitToDeleteId)->delete();
        }
        
        $this->isDeleteModalOpen = false;
        $this->unitToDeleteId = null;
    }

    // ==========================================
    // FUNGSI: READ (MENAMPILKAN DATA BERJENJANG)
    // ==========================================
    public function with(): array
    {
        // ==========================================
        // 1. LOGIKA DATA UNIT (TABEL UTAMA)
        // ==========================================
        if ($this->search) {
            // Mode Pencarian: Tampilkan data secara flat (datar)
            $paginator = Unit::with(['parent', 'kepalaUnit', 'members'])
                ->where('nama_unit', 'like', '%' . $this->search . '%')
                ->orWhere('kode_unit', 'like', '%' . $this->search . '%')
                ->orderBy('nama_unit')
                ->paginate(10);
                
            $paginator->getCollection()->transform(function($unit) {
                $unit->level = 0; 
                return $unit;
            });
            
            $units = $paginator;
        } else {
            // Mode Standar: Tampilkan data secara Hierarkis (Berjenjang)
            $topLevelPaginator = Unit::with(['parent', 'kepalaUnit', 'members'])
                ->whereNull('parent_id')
                ->orderBy('nama_unit')
                ->paginate(10);
                
            $allDescendants = Unit::with(['parent', 'kepalaUnit', 'members'])
                ->whereNotNull('parent_id')
                ->orderBy('nama_unit')
                ->get();
                
            $sortedUnits = collect();
            
            $buildTree = function($parentId, $level) use (&$buildTree, $allDescendants, &$sortedUnits) {
                if ($level > 10) return; 
                foreach($allDescendants->where('parent_id', $parentId) as $unit) {
                    $unit->level = $level;
                    $sortedUnits->push($unit);
                    $buildTree($unit->id, $level + 1);
                }
            };

            foreach($topLevelPaginator as $topUnit) {
                $topUnit->level = 0;
                $sortedUnits->push($topUnit);
                $buildTree($topUnit->id, 1);
            }
            
            $units = new \Illuminate\Pagination\LengthAwarePaginator(
                $sortedUnits,
                $topLevelPaginator->total(),
                $topLevelPaginator->perPage(),
                $topLevelPaginator->currentPage(),
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
            );
        }

        // ==========================================
        // 2. LOGIKA DATA USER (PLOTTING STAFF)
        // ==========================================
        // Menggunakan searchStaff untuk menyaring 100+ user agar mudah dicari
        $usersForPlotting = User::query()
            ->when($this->searchStaff, function($query) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->searchStaff . '%')
                    ->orWhere('email', 'like', '%' . $this->searchStaff . '%');
                });
            })
            ->orderBy('name')
            ->limit(50) // Membatasi beban DOM, user lain bisa dicari via input search
            ->get();

        return [
            'units' => $units,
            
            // Pilihan Induk: Mencegah unit menjadi anak dari dirinya sendiri
            'allUnits' => Unit::when($this->unitId, function($query) {
                                return $query->where('id', '!=', $this->unitId);
                            })->orderBy('nama_unit')->get(),
                            
            // Data User yang sudah difilter untuk modal plotting
            'users' => $usersForPlotting, 
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Kelola Unit Kerja</h1>
            <p class="text-sm text-theme-muted mt-1">Manajemen Biro, Lembaga, Fakultas, dan Program Studi.</p>
        </div>
        
        <button wire:click="openModal" class="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Unit
        </button>
    </div>

    <!-- Kotak Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex items-center gap-3">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            
            <input 
                type="text" 
                wire:model.live="search" 
                autocomplete="off" 
                class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all" 
                placeholder="Cari nama atau kode unit..."
            >
        </div>
    </div>

    <!-- Tabel Data (READ) -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Kode</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Nama Unit</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Induk (Parent)</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Kepala Unit</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Jumlah Staff</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($units as $unit)
                        <tr class="hover:bg-theme-body/30 transition-colors {{ (isset($unit->level) && $unit->level == 0) ? 'bg-theme-body/10' : '' }}">
                            <!-- Kode Unit -->
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-theme-body border border-theme-border text-theme-text font-mono">
                                    {{ $unit->kode_unit ?: '-' }}
                                </span>
                            </td>
                            
                            <!-- Nama Unit (Dengan Indentasi Visual & Ikon) -->
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    @if(isset($unit->level) && $unit->level > 0)
                                        <!-- Spacer untuk level kedalaman -->
                                        <div style="width: {{ ($unit->level - 1) * 1.5 }}rem;" class="flex-shrink-0"></div>
                                        <!-- Garis penghubung L-Shape -->
                                        <div class="w-4 h-4 border-b-2 border-l-2 border-theme-muted rounded-bl mr-3 -mt-2 flex-shrink-0"></div>
                                    @endif
                                    
                                    <div class="text-sm font-bold {{ (isset($unit->level) && $unit->level == 0) ? 'text-primary' : 'text-theme-text' }}">
                                        {{ $unit->nama_unit }}
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Unit Induk -->
                            <td class="px-6 py-4">
                                @if($unit->parent)
                                    <div class="flex items-center gap-2 text-sm text-theme-text">
                                        {{ $unit->parent->nama_unit }}
                                    </div>
                                @else
                                    <span class="text-xs font-bold uppercase tracking-wider text-theme-muted">Top Level</span>
                                @endif
                            </td>

                            <!-- Kepala Unit -->
                            <td class="px-6 py-4">
                                @if($unit->kepalaUnit)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center text-[10px] font-bold">
                                            {{ substr($unit->kepalaUnit->name, 0, 1) }}
                                        </div>
                                        <div class="text-sm font-medium text-theme-text">{{ $unit->kepalaUnit->name }}</div>
                                    </div>
                                @else
                                    <span class="text-xs italic text-red-500 bg-red-50 dark:bg-red-500/10 px-2 py-1 rounded border border-red-200 dark:border-red-500/20">Belum Diatur</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 text-sm text-theme-text">
                                    <svg class="w-4 h-4 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span class="font-bold">{{ $unit->members->count() }} Orang</span>
                                </div>
                            </td>
                            <!-- Aksi -->
                            <td class="px-6 py-4 text-right space-x-1 whitespace-nowrap">
                                <!-- TOMBOL DETAIL -->
                                <a href="{{ route('admin.units.detail', $unit) }}" class="text-theme-muted hover:text-blue-500 transition-colors p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-500/10 border border-transparent hover:border-blue-200 dark:hover:border-blue-500/20 inline-flex items-center justify-center" title="Detail Unit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </a>

                                <!-- TOMBOL EDIT -->
                                <button wire:click="openModal({{ $unit->id }})" class="text-theme-muted hover:text-primary transition-colors p-2 rounded-lg hover:bg-theme-body border border-transparent hover:border-theme-border" title="Edit Unit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>

                                <!-- TOMBOL HAPUS -->
                                <button wire:click="confirmDelete({{ $unit->id }})" class="text-theme-muted hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/20" title="Hapus Unit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-theme-muted">
                                Tidak ada unit kerja yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginasi -->
        @if($units->hasPages())
            <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">
                {{ $units->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- MODAL FORM (TAMBAH / EDIT UNIT) -->
    <!-- ========================================== -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4 py-6">
            <!-- Container Utama: Menggunakan flex-col dan max-height agar bisa di-scroll -->
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh] overflow-hidden">
                
                <!-- 1. FIXED HEADER: Tidak ikut bergeser saat di-scroll -->
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30 flex justify-between items-center flex-shrink-0">
                    <h3 class="text-lg font-bold text-theme-text">
                        {{ $unitId ? 'Edit Unit Kerja' : 'Tambah Unit Baru' }}
                    </h3>
                    <button wire:click="$set('isModalOpen', false)" class="text-theme-muted hover:text-theme-text transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- 2. SCROLLABLE BODY: Area pengisian form -->
                <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                    <form wire:submit="saveUnit" id="unitForm" class="space-y-5">
                        <!-- Baris 1: Kode & Nama -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Kode Unit <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="kode_unit" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-mono uppercase" placeholder="Contoh: LPPM">
                                @error('kode_unit') <span class="text-[10px] text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Nama Unit <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="nama_unit" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="Lembaga Penelitian...">
                                @error('nama_unit') <span class="text-[10px] text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Unit Induk -->
                        <div>
                            <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Unit Induk (Bawahan Dari)</label>
                            <select wire:model="parent_id" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                <option value="">-- Berdiri Sendiri (Top Level) --</option>
                                @foreach($allUnits as $u)
                                    <option value="{{ $u->id }}">{{ $u->kode_unit ? '['.$u->kode_unit.'] ' : '' }}{{ $u->nama_unit }}</option>
                                @endforeach
                            </select>
                            @error('parent_id') <span class="text-[10px] text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Kepala Unit -->
                        <div>
                            <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Pejabat / Kepala Unit</label>
                            <select wire:model="kepala_unit_id" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                <option value="">-- Belum Ditentukan --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('kepala_unit_id') <span class="text-[10px] text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        </div>

                        <hr class="border-theme-border border-dashed my-4">

                        <!-- SEKSI PLOTTING STAFF DENGAN SEARCH -->
                        <div class="space-y-3">
                            <div class="flex justify-between items-end">
                                <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider">
                                    Plotting Anggota Staff
                                </label>
                                <span class="text-[10px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-full">
                                    {{ count($staffIds) }} Terpilih
                                </span>
                            </div>

                            <!-- Mini Search Internal Staff -->
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </span>
                                <input 
                                    type="text" 
                                    wire:model.live="searchStaff" 
                                    placeholder="Cari nama staff..." 
                                    class="block w-full pl-9 pr-3 py-2 border border-theme-border bg-theme-body rounded-xl text-xs focus:ring-primary focus:border-primary text-theme-text"
                                >
                            </div>
                            
                            <!-- List Checkbox Staff (Scrollable Box) -->
                            <div class="max-h-52 overflow-y-auto border border-theme-border bg-theme-body/50 rounded-xl p-1 space-y-1 custom-scrollbar">
                                @forelse($users as $user)
                                    <label class="flex items-center gap-3 p-2 hover:bg-theme-surface rounded-lg cursor-pointer transition-colors group">
                                        <input 
                                            type="checkbox" 
                                            wire:model="staffIds" 
                                            value="{{ $user->id }}"
                                            class="w-4 h-4 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface"
                                        >
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-theme-text group-hover:text-primary transition-colors">
                                                {{ $user->name }}
                                            </span>
                                            <span class="text-[10px] text-theme-muted uppercase tracking-widest">
                                                {{ $user->email }}
                                            </span>
                                        </div>
                                    </label>
                                @empty
                                    <div class="p-4 text-center text-xs text-theme-muted italic">
                                        Staff tidak ditemukan.
                                    </div>
                                @endforelse
                            </div>
                            <p class="text-[9px] text-theme-muted italic leading-tight">
                                * Gunakan pencarian jika staff yang dimaksud tidak muncul di daftar awal.
                            </p>
                        </div>
                    </form>
                </div>
                
                <!-- 3. FIXED FOOTER: Selalu di posisi bawah modal -->
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/30 flex justify-end gap-3 flex-shrink-0">
                    <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">
                        Batal
                    </button>
                    <!-- Gunakan atribut form="unitForm" agar tombol di luar tag form bisa melakukan submit -->
                    <button type="submit" form="unitForm" class="px-5 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md shadow-primary/20 transition-all">
                        {{ $unitId ? 'Simpan Perubahan' : 'Tambah Unit' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL DELETE (KONFIRMASI HAPUS) -->
    <!-- ========================================== -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-sm overflow-hidden text-center p-6">
                <div class="w-16 h-16 bg-red-100 dark:bg-red-500/20 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-theme-text mb-2">Hapus Unit Kerja?</h3>
                <p class="text-sm text-theme-muted mb-6">Apakah Anda yakin ingin menghapus unit ini? Unit yang dihapus tidak akan muncul lagi di sistem.</p>
                <div class="flex justify-center gap-3">
                    <button wire:click="$set('isDeleteModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-theme-muted hover:text-theme-text bg-theme-body rounded-xl border border-theme-border transition-colors w-full">
                        Batal
                    </button>
                    <button wire:click="deleteUnit" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-md transition-all w-full">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>