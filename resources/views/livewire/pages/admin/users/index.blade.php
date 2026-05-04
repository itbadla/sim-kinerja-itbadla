<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Unit;
use Spatie\Permission\Models\Role;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    // ==========================================
    // STATE: MODAL TAMBAH (CREATE)
    // ==========================================
    public $isCreateModalOpen = false;
    public $newName = '';
    public $newEmail = '';
    public $newPassword = '';
    public $newRoles = [];
    public $newUnitId = '';
    public $newJabatan = '';
    public $headedUnitIds = []; // Array untuk menampung ID unit yang dipimpin

    // ==========================================
    // STATE: MODAL EDIT (UPDATE)
    // ==========================================
    public $isEditModalOpen = false;
    public ?User $selectedUser = null;
    public $editName = '';
    public $editEmail = '';
    public $editPassword = ''; 
    public $editRoles = [];
    public $editUnitId = '';
    public $editJabatan = '';

    // ==========================================
    // STATE: MODAL HAPUS (DELETE)
    // ==========================================
    public $isDeleteModalOpen = false;
    public ?int $userToDeleteId = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    // ==========================================
    // FUNGSI: CREATE (TAMBAH USER)
    // ==========================================
    public function openCreateModal()
    {
        $this->reset(['newName', 'newEmail', 'newPassword', 'newRoles', 'newUnitId', 'newJabatan', 'headedUnitIds']);
        $this->resetValidation(); 
        $this->isCreateModalOpen = true;
    }

    public function saveNewUser()
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newEmail' => 'required|email|unique:users,email',
            'newPassword' => 'required|min:8',
            'newUnitId' => 'nullable|exists:units,id',
            'newJabatan' => 'nullable|string|max:255',
            'headedUnitIds' => 'nullable|array',
            'headedUnitIds.*' => 'exists:units,id',
        ]);

        $user = User::create([
            'name' => $this->newName,
            'email' => $this->newEmail,
            'password' => bcrypt($this->newPassword),
            'email_verified_at' => now(),
            'unit_id' => $this->newUnitId ?: null,
            'jabatan' => $this->newJabatan,
        ]);

        // Assign multiple roles
        if (!empty($this->newRoles)) {
            $user->assignRole($this->newRoles);
        }

        // Plotting sebagai Kepala Unit di banyak unit sekaligus
        if (!empty($this->headedUnitIds)) {
            Unit::whereIn('id', $this->headedUnitIds)->update(['kepala_unit_id' => $user->id]);
        }

        $this->isCreateModalOpen = false;
    }

    // ==========================================
    // FUNGSI: UPDATE (EDIT USER & ROLE)
    // ==========================================
    public function openEditModal(User $user)
    {
        $this->resetValidation();
        $this->selectedUser = $user;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->editUnitId = $user->unit_id ?? '';
        $this->editJabatan = $user->jabatan ?? '';
        
        // Ambil semua role
        $this->editRoles = $user->roles->pluck('name')->toArray(); 
        
        // Ambil semua unit di mana user ini menjadi kepala unit
        $this->headedUnitIds = Unit::where('kepala_unit_id', $user->id)->pluck('id')->toArray();

        $this->editPassword = ''; 
        $this->isEditModalOpen = true;
    }

    public function updateUser()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editEmail' => 'required|email|unique:users,email,' . $this->selectedUser->id,
            'editPassword' => 'nullable|min:8',
            'editUnitId' => 'nullable|exists:units,id',
            'editJabatan' => 'nullable|string|max:255',
            'headedUnitIds' => 'nullable|array',
            'headedUnitIds.*' => 'exists:units,id',
        ]);

        // Update data dasar
        $this->selectedUser->name = $this->editName;
        $this->selectedUser->email = $this->editEmail;
        $this->selectedUser->unit_id = $this->editUnitId ?: null;
        $this->selectedUser->jabatan = $this->editJabatan;
        
        if (!empty($this->editPassword)) {
            $this->selectedUser->password = bcrypt($this->editPassword);
        }
        
        $this->selectedUser->save();

        // Sync roles 
        $this->selectedUser->syncRoles($this->editRoles);
        
        // Sync Kepala Unit: Kosongkan dulu semua unit yang dipegang user ini
        Unit::where('kepala_unit_id', $this->selectedUser->id)->update(['kepala_unit_id' => null]);
        // Kemudian set kembali untuk unit-unit yang baru saja dipilih
        if (!empty($this->headedUnitIds)) {
            Unit::whereIn('id', $this->headedUnitIds)->update(['kepala_unit_id' => $this->selectedUser->id]);
        }

        $this->isEditModalOpen = false;
    }

    // ==========================================
    // FUNGSI: DELETE (HAPUS USER)
    // ==========================================
    public function confirmDelete($id)
    {
        $this->userToDeleteId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function deleteUser()
    {
        if ($this->userToDeleteId) {
            // Relasi nullOnDelete di database akan otomatis mengosongkan kepala_unit_id
            User::findOrFail($this->userToDeleteId)->delete();
        }
        
        $this->isDeleteModalOpen = false;
        $this->userToDeleteId = null;
    }

    // ==========================================
    // FUNGSI: IMPERSONATE (LOGIN SEBAGAI)
    // ==========================================
    public function impersonate($id)
    {
        $userToImpersonate = User::findOrFail($id);

        if ($userToImpersonate->id === auth()->id()) {
            return;
        }

        session()->put('impersonated_by', auth()->id());
        auth()->login($userToImpersonate);

        return redirect()->route('dashboard');
    }

    // ==========================================
    // FUNGSI: READ (MENAMPILKAN DATA)
    // ==========================================
    public function with(): array
    {
        return [
            'users' => User::with(['roles', 'unit'])
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('email', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10),
            'availableRoles' => Role::all(), 
            'availableUnits' => Unit::orderBy('nama_unit')->get(),
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Kelola Pengguna</h1>
            <p class="text-sm text-theme-muted mt-1">Manajemen Dosen, Tendik, Unit, dan Hak Akses Sistem.</p>
        </div>
        
        <button wire:click="openCreateModal" class="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah User
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
                placeholder="Cari nama atau email..."
            >
        </div>
    </div>

    <!-- Tabel Data (READ) -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-1/3">Pengguna</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-1/3">Unit & Jabatan</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Peran (Role)</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="hover:bg-theme-body/30 transition-colors">
                            <!-- Kolom Pengguna -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img class="w-10 h-10 rounded-full object-cover border border-theme-border" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&color=059669&background=ECFDF5&bold=true" alt="">
                                    <div>
                                        <div class="text-sm font-bold text-theme-text">{{ $user->name }}</div>
                                        <div class="text-xs text-theme-muted">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kolom Unit & Jabatan (Multi Jabatan Terlihat di Sini) -->
                            <td class="px-6 py-4 align-top">
                                <!-- Homebase & Jabatan Utama -->
                                @if($user->unit)
                                    <div class="text-sm font-bold text-theme-text flex items-center gap-1.5">
                                        {{ $user->unit->nama_unit }}
                                        <span class="px-1.5 py-0.5 rounded text-[9px] uppercase tracking-widest bg-theme-body border border-theme-border text-theme-muted shrink-0">Homebase</span>
                                    </div>
                                    <div class="text-xs text-theme-muted mb-2">{{ $user->jabatan ?? 'Staf' }}</div>
                                @else
                                    <span class="text-xs italic text-theme-muted block mb-2">Belum ada homebase</span>
                                @endif

                                <!-- Badge Jabatan Struktural / Kepala Unit -->
                                @php
                                    // Cari unit mana saja yang dipimpin oleh user ini
                                    $headedUnits = $availableUnits->where('kepala_unit_id', $user->id);
                                @endphp

                                @if($headedUnits->count() > 0)
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @foreach($headedUnits as $hUnit)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Kepala {{ $hUnit->kode_unit ?? $hUnit->nama_unit }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <!-- Kolom Role -->
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($user->roles as $role)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider {{ $role->name === 'admin' ? 'bg-theme-text text-theme-body' : 'bg-theme-body border border-theme-border text-theme-text' }}">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs italic text-theme-muted">Tanpa role</span>
                                    @endforelse
                                </div>
                            </td>

                            <!-- Kolom Aksi -->
                            <td class="px-6 py-4 align-top text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- TOMBOL IMPERSONATE (LOGIN SEBAGAI) -->
                                    @if($user->id !== auth()->id())
                                        <button wire:click="impersonate({{ $user->id }})" class="text-theme-muted hover:text-green-600 transition-colors p-2 rounded-lg hover:bg-green-50 border border-transparent hover:border-green-200 dark:hover:bg-green-500/10 dark:hover:border-green-500/20" title="Login sebagai {{ $user->name }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                    @endif

                                    <!-- TOMBOL EDIT -->
                                    <button wire:click="openEditModal({{ $user->id }})" class="text-theme-muted hover:text-primary transition-colors p-2 rounded-lg hover:bg-theme-body border border-transparent hover:border-theme-border" title="Edit Pengguna">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>

                                    <!-- TOMBOL HAPUS -->
                                    <button wire:click="confirmDelete({{ $user->id }})" class="text-theme-muted hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/20" title="Hapus Pengguna">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-theme-muted">
                                Tidak ada pengguna yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- MODAL CREATE (TAMBAH USER) -->
    <!-- ========================================== -->
    @if($isCreateModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4 py-6 overflow-y-auto">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl my-auto">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30">
                    <h3 class="text-lg font-bold text-theme-text">Tambah Pengguna Baru</h3>
                </div>
                <div class="p-6 space-y-5">
                    
                    <!-- Bagian Info Pribadi -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Nama Lengkap</label>
                            <input type="text" wire:model="newName" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                            @error('newName') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Alamat Email</label>
                            <input type="email" wire:model="newEmail" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                            @error('newEmail') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Password Sementara</label>
                            <input type="password" wire:model="newPassword" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                            @error('newPassword') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <hr class="border-theme-border border-dashed">

                    <!-- Bagian Homebase Utama -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text mb-3">Penempatan Utama (Homebase)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-xs text-theme-muted mb-2">Unit Kerja Asal</label>
                                <select wire:model="newUnitId" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                    <option value="">-- Tidak Terikat Unit --</option>
                                    @foreach($availableUnits as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->kode_unit ? '['.$unit->kode_unit.'] ' : '' }}{{ $unit->nama_unit }}</option>
                                    @endforeach
                                </select>
                                @error('newUnitId') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-xs text-theme-muted mb-2">Jabatan / Gelar</label>
                                <input type="text" wire:model="newJabatan" placeholder="Contoh: Staf IT, Dosen Tetap..." class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                @error('newJabatan') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="border-theme-border border-dashed">

                    <!-- Bagian Multi Jabatan (Kepala Unit) -->
                    <div>
                        <div class="mb-3">
                            <h4 class="text-sm font-bold text-theme-text">Jabatan Struktural / Pimpinan Unit</h4>
                            <p class="text-[10px] text-theme-muted mt-0.5">Centang unit di bawah ini jika user memimpin/menjadi kepala pada unit tersebut.</p>
                        </div>
                        <div class="max-h-32 overflow-y-auto custom-scrollbar border border-theme-border rounded-xl p-2 bg-theme-body/50">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($availableUnits as $unit)
                                    <label class="flex items-start gap-2 cursor-pointer p-2 hover:bg-theme-surface rounded-lg transition-colors border border-transparent hover:border-theme-border">
                                        <input type="checkbox" wire:model="headedUnitIds" value="{{ $unit->id }}" class="w-4 h-4 mt-0.5 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface">
                                        <div>
                                            <span class="text-xs font-bold text-theme-text block">{{ $unit->kode_unit ?? $unit->nama_unit }}</span>
                                            <span class="text-[10px] text-theme-muted line-clamp-1" title="{{ $unit->nama_unit }}">{{ $unit->nama_unit }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- MULTIPLE ROLE CHECKBOXES -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text mb-3">Hak Akses Sistem (Role)</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mt-2 bg-theme-body border border-theme-border p-3 rounded-xl">
                            @foreach($availableRoles as $role)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="newRoles" value="{{ $role->name }}" class="w-4 h-4 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface">
                                    <span class="text-sm font-medium text-theme-text">{{ strtoupper($role->name) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                </div>
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/30 flex justify-end gap-3 sticky bottom-0">
                    <button wire:click="$set('isCreateModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                    <button wire:click="saveNewUser" class="px-4 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all">Simpan Data</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL UPDATE (EDIT USER) -->
    <!-- ========================================== -->
    @if($isEditModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4 py-6 overflow-y-auto">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl my-auto">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30">
                    <h3 class="text-lg font-bold text-theme-text">Edit Pengguna</h3>
                </div>
                <div class="p-6 space-y-5">
                    
                    <!-- Bagian Info Pribadi -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Nama Lengkap</label>
                            <input type="text" wire:model="editName" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                            @error('editName') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Alamat Email</label>
                            <input type="email" wire:model="editEmail" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                            @error('editEmail') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Password Baru <span class="text-[10px] text-theme-muted normal-case font-normal">(Kosongkan jika tidak diubah)</span></label>
                            <input type="password" wire:model="editPassword" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="Masukkan password baru">
                            @error('editPassword') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <hr class="border-theme-border border-dashed">

                    <!-- Bagian Homebase Utama -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text mb-3">Penempatan Utama (Homebase)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-xs text-theme-muted mb-2">Unit Kerja Asal</label>
                                <select wire:model="editUnitId" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                    <option value="">-- Tidak Terikat Unit --</option>
                                    @foreach($availableUnits as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->kode_unit ? '['.$unit->kode_unit.'] ' : '' }}{{ $unit->nama_unit }}</option>
                                    @endforeach
                                </select>
                                @error('editUnitId') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-xs text-theme-muted mb-2">Jabatan / Gelar</label>
                                <input type="text" wire:model="editJabatan" placeholder="Contoh: Staf IT, Dosen Tetap..." class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                @error('editJabatan') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="border-theme-border border-dashed">

                    <!-- Bagian Multi Jabatan (Kepala Unit) -->
                    <div>
                        <div class="mb-3">
                            <h4 class="text-sm font-bold text-theme-text">Jabatan Struktural / Pimpinan Unit</h4>
                            <p class="text-[10px] text-theme-muted mt-0.5">Centang unit di bawah ini jika user memimpin/menjadi kepala pada unit tersebut.</p>
                        </div>
                        <div class="max-h-32 overflow-y-auto custom-scrollbar border border-theme-border rounded-xl p-2 bg-theme-body/50">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($availableUnits as $unit)
                                    <label class="flex items-start gap-2 cursor-pointer p-2 hover:bg-theme-surface rounded-lg transition-colors border border-transparent hover:border-theme-border">
                                        <input type="checkbox" wire:model="headedUnitIds" value="{{ $unit->id }}" class="w-4 h-4 mt-0.5 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface">
                                        <div>
                                            <span class="text-xs font-bold text-theme-text block">{{ $unit->kode_unit ?? $unit->nama_unit }}</span>
                                            <span class="text-[10px] text-theme-muted line-clamp-1" title="{{ $unit->nama_unit }}">{{ $unit->nama_unit }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <!-- MULTIPLE ROLE CHECKBOXES -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text mb-3">Hak Akses Sistem (Role)</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mt-2 bg-theme-body border border-theme-border p-3 rounded-xl">
                            @foreach($availableRoles as $role)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="editRoles" value="{{ $role->name }}" class="w-4 h-4 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface">
                                    <span class="text-sm font-medium text-theme-text">{{ strtoupper($role->name) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                </div>
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/30 flex justify-end gap-3 sticky bottom-0">
                    <button wire:click="$set('isEditModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                    <button wire:click="updateUser" class="px-4 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all">Simpan Perubahan</button>
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
                <h3 class="text-xl font-bold text-theme-text mb-2">Hapus Pengguna?</h3>
                <p class="text-sm text-theme-muted mb-6">Apakah Anda yakin ingin menghapus akun ini secara permanen? Data yang dihapus tidak dapat dikembalikan.</p>
                <div class="flex justify-center gap-3">
                    <button wire:click="$set('isDeleteModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-theme-muted hover:text-theme-text bg-theme-body rounded-xl border border-theme-border transition-colors w-full">
                        Batal
                    </button>
                    <button wire:click="deleteUser" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-md transition-all w-full">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>