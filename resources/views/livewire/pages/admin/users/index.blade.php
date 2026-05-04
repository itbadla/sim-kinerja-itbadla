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
    public $headedUnitIds = []; 

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

        // Otomatis masukkan sebagai Staff di Homebase-nya
        if ($this->newUnitId) {
            $unit = Unit::find($this->newUnitId);
            if ($unit) {
                $unit->members()->syncWithoutDetaching([$user->id]);
            }
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
        
        $this->editRoles = $user->roles->pluck('name')->toArray(); 
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
        $this->selectedUser->syncRoles($this->editRoles);
        
        // Otomatis masukkan sebagai Staff di Homebase-nya jika berubah
        if ($this->editUnitId) {
            $unit = Unit::find($this->editUnitId);
            if ($unit) {
                $unit->members()->syncWithoutDetaching([$this->selectedUser->id]);
            }
        }

        // Sync Kepala Unit
        Unit::where('kepala_unit_id', $this->selectedUser->id)->update(['kepala_unit_id' => null]);
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
            User::findOrFail($this->userToDeleteId)->delete();
        }
        
        $this->isDeleteModalOpen = false;
        $this->userToDeleteId = null;
    }

    public function impersonate($id)
    {
        $userToImpersonate = User::findOrFail($id);
        if ($userToImpersonate->id === auth()->id()) return;
        session()->put('impersonated_by', auth()->id());
        auth()->login($userToImpersonate);
        return redirect()->route('dashboard');
    }

    public function with(): array
    {
        return [
            // Tambahkan relasi 'assignedUnits' agar bisa mendeteksi dia jadi staff di unit mana saja
            'users' => User::with(['roles', 'unit', 'assignedUnits'])
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
                class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary text-sm text-theme-text transition-all" 
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
                                    <div class="w-10 h-10 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-bold shadow-inner">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-theme-text">{{ $user->name }}</div>
                                        <div class="text-xs text-theme-muted">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kolom Unit & Jabatan -->
                            <td class="px-6 py-4 align-top">
                                <!-- Homebase & Jabatan Utama -->
                                @if($user->unit)
                                    <div class="mb-1">
                                        <span class="text-sm font-bold text-theme-text">{{ $user->unit->nama_unit }}</span>
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] uppercase tracking-widest bg-theme-body border border-theme-border text-theme-muted">Homebase</span>
                                    </div>
                                    <div class="text-xs text-theme-muted mb-3">{{ $user->jabatan ?? 'Staf' }}</div>
                                @else
                                    <span class="text-xs italic text-theme-muted block mb-3">Belum ada homebase</span>
                                @endif

                                <div class="flex flex-col gap-1.5">
                                    <!-- Daftar Penugasan (Struktural & Staff) -->
                                    @php
                                        // Cari unit mana saja yang DIPIMPIN (Kepala)
                                        $headedUnits = $availableUnits->where('kepala_unit_id', $user->id);
                                    @endphp

                                    <!-- Badge Jabatan Struktural / Kepala Unit -->
                                    @foreach($headedUnits as $hUnit)
                                        <span class="inline-flex w-max items-center gap-1.5 px-2 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Kepala {{ $hUnit->kode_unit ?? $hUnit->nama_unit }}
                                        </span>
                                    @endforeach

                                    <!-- Badge Staff / Anggota (Dari tabel Pivot assignedUnits) -->
                                    @foreach($user->assignedUnits as $sUnit)
                                        <!-- Cek agar tidak double label jika dia sudah menjadi kepala di unit ini -->
                                        @if(!$headedUnits->contains('id', $sUnit->id))
                                            <span class="inline-flex w-max items-center gap-1.5 px-2 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                Staff {{ $sUnit->kode_unit ?? $sUnit->nama_unit }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>

                            <!-- Kolom Role -->
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($user->roles as $role)
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider {{ $role->name === 'admin' ? 'bg-theme-text text-theme-body' : 'bg-theme-body border border-theme-border text-theme-text' }}">
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
                                    @if($user->id !== auth()->id())
                                        <button wire:click="impersonate({{ $user->id }})" class="text-theme-muted hover:text-green-600 p-2 rounded-lg hover:bg-green-50 border border-transparent" title="Login sebagai {{ $user->name }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                    @endif
                                    <button wire:click="openEditModal({{ $user->id }})" class="text-theme-muted hover:text-primary p-2 rounded-lg hover:bg-theme-body border border-transparent" title="Edit Pengguna">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button wire:click="confirmDelete({{ $user->id }})" class="text-theme-muted hover:text-red-500 p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent" title="Hapus Pengguna">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-theme-muted">Tidak ada pengguna yang ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">{{ $users->links() }}</div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- MODAL CREATE (TAMBAH USER) -->
    <!-- ========================================== -->
    @if($isCreateModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden">
                <!-- Header Sticky -->
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center z-10 shrink-0">
                    <h3 class="text-lg font-bold text-theme-text">Tambah Pengguna Baru</h3>
                    <button wire:click="$set('isCreateModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- Body Scrollable -->
                <div class="p-6 overflow-y-auto custom-scrollbar space-y-6">
                    <!-- Info Pribadi -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-4">Informasi Akun</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                                <input type="text" wire:model="newName" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                                @error('newName') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Alamat Email</label>
                                <input type="email" wire:model="newEmail" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                                @error('newEmail') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Password Sementara</label>
                                <input type="password" wire:model="newPassword" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                                @error('newPassword') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Homebase Utama -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-4">Struktur & Penempatan Utama</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Unit Kerja Asal (Homebase)</label>
                                <select wire:model="newUnitId" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                                    <option value="">-- Tidak Terikat Unit --</option>
                                    @foreach($availableUnits as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->kode_unit ? '['.$unit->kode_unit.'] ' : '' }}{{ $unit->nama_unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Jabatan / Gelar</label>
                                <input type="text" wire:model="newJabatan" placeholder="Contoh: Staf IT, Dosen Tetap..." class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                            </div>
                        </div>
                    </div>

                    <!-- Multi Jabatan (Kepala Unit) -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-3 flex items-center gap-2">
                            Amanah Tambahan (Struktural)
                            <span class="text-[9px] font-normal px-2 py-0.5 bg-blue-50 text-blue-600 rounded border border-blue-200">Opsional</span>
                        </h4>
                        <p class="text-xs text-theme-muted mb-3">Centang unit di bawah ini jika user menjabat sebagai <strong>Kepala/Pimpinan</strong> pada unit tersebut.</p>
                        
                        <div class="max-h-48 overflow-y-auto custom-scrollbar border border-theme-border rounded-xl p-3 bg-theme-body/30">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($availableUnits as $unit)
                                    <label class="flex items-start gap-3 cursor-pointer p-2.5 hover:bg-theme-surface rounded-xl transition-colors border border-transparent hover:border-theme-border group">
                                        <input type="checkbox" wire:model="headedUnitIds" value="{{ $unit->id }}" class="w-4 h-4 mt-0.5 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface">
                                        <div>
                                            <span class="text-xs font-bold text-theme-text block group-hover:text-primary transition-colors">{{ $unit->kode_unit ?? $unit->nama_unit }}</span>
                                            <span class="text-[10px] text-theme-muted line-clamp-1 mt-0.5" title="{{ $unit->nama_unit }}">{{ $unit->nama_unit }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Hak Akses Sistem -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-3">Hak Akses Sistem (Role Spatie)</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($availableRoles as $role)
                                <label class="flex items-center gap-2 cursor-pointer p-2 border border-theme-border rounded-xl hover:bg-theme-body transition-colors">
                                    <input type="checkbox" wire:model="newRoles" value="{{ $role->name }}" class="w-4 h-4 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface">
                                    <span class="text-xs font-bold text-theme-text uppercase tracking-wider">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Footer Sticky -->
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-end gap-3 shrink-0">
                    <button wire:click="$set('isCreateModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                    <button wire:click="saveNewUser" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-lg transition-all">Simpan Pengguna</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL UPDATE (EDIT USER) -->
    <!-- ========================================== -->
    @if($isEditModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden">
                <!-- Header Sticky -->
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center z-10 shrink-0">
                    <h3 class="text-lg font-bold text-theme-text">Edit Data Pengguna</h3>
                    <button wire:click="$set('isEditModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- Body Scrollable -->
                <div class="p-6 overflow-y-auto custom-scrollbar space-y-6">
                    <!-- Info Pribadi -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-4">Informasi Akun</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                                <input type="text" wire:model="editName" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                                @error('editName') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Alamat Email</label>
                                <input type="email" wire:model="editEmail" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                                @error('editEmail') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Password Baru <span class="text-[10px] text-theme-muted normal-case font-normal">(Kosongkan jika tidak diubah)</span></label>
                                <input type="password" wire:model="editPassword" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text" placeholder="Masukkan password baru...">
                                @error('editPassword') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Homebase Utama -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-4">Struktur & Penempatan Utama</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Unit Kerja Asal (Homebase)</label>
                                <select wire:model="editUnitId" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                                    <option value="">-- Tidak Terikat Unit --</option>
                                    @foreach($availableUnits as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->kode_unit ? '['.$unit->kode_unit.'] ' : '' }}{{ $unit->nama_unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Jabatan / Gelar</label>
                                <input type="text" wire:model="editJabatan" placeholder="Contoh: Staf IT, Dosen Tetap..." class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm focus:ring-primary text-theme-text">
                            </div>
                        </div>
                    </div>

                    <!-- Multi Jabatan (Kepala Unit) -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-3 flex items-center gap-2">
                            Amanah Tambahan (Struktural)
                            <span class="text-[9px] font-normal px-2 py-0.5 bg-blue-50 text-blue-600 rounded border border-blue-200">Opsional</span>
                        </h4>
                        <p class="text-xs text-theme-muted mb-3">Centang unit di bawah ini jika user menjabat sebagai <strong>Kepala/Pimpinan</strong> pada unit tersebut.</p>
                        
                        <div class="max-h-48 overflow-y-auto custom-scrollbar border border-theme-border rounded-xl p-3 bg-theme-body/30">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($availableUnits as $unit)
                                    <label class="flex items-start gap-3 cursor-pointer p-2.5 hover:bg-theme-surface rounded-xl transition-colors border border-transparent hover:border-theme-border group">
                                        <input type="checkbox" wire:model="headedUnitIds" value="{{ $unit->id }}" class="w-4 h-4 mt-0.5 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface">
                                        <div>
                                            <span class="text-xs font-bold text-theme-text block group-hover:text-primary transition-colors">{{ $unit->kode_unit ?? $unit->nama_unit }}</span>
                                            <span class="text-[10px] text-theme-muted line-clamp-1 mt-0.5" title="{{ $unit->nama_unit }}">{{ $unit->nama_unit }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hak Akses Sistem -->
                    <div>
                        <h4 class="text-sm font-bold text-theme-text border-b border-theme-border pb-2 mb-3">Hak Akses Sistem (Role Spatie)</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($availableRoles as $role)
                                <label class="flex items-center gap-2 cursor-pointer p-2 border border-theme-border rounded-xl hover:bg-theme-body transition-colors">
                                    <input type="checkbox" wire:model="editRoles" value="{{ $role->name }}" class="w-4 h-4 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface">
                                    <span class="text-xs font-bold text-theme-text uppercase tracking-wider">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Footer Sticky -->
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-end gap-3 shrink-0">
                    <button wire:click="$set('isEditModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                    <button wire:click="updateUser" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-lg transition-all">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL DELETE (KONFIRMASI HAPUS) -->
    <!-- ========================================== -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm px-4" style="pointer-events: auto;">
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