<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
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
    public $newRole = '';

    // ==========================================
    // STATE: MODAL EDIT (UPDATE)
    // ==========================================
    public $isEditModalOpen = false;
    public ?User $selectedUser = null;
    public $editName = '';
    public $editEmail = '';
    public $editPassword = ''; // Kosongkan, hanya diisi jika ingin diubah
    public $editRole = '';

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
        $this->reset(['newName', 'newEmail', 'newPassword', 'newRole']);
        $this->resetValidation(); 
        $this->isCreateModalOpen = true;
    }

    public function saveNewUser()
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newEmail' => 'required|email|unique:users,email',
            'newPassword' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $this->newName,
            'email' => $this->newEmail,
            'password' => bcrypt($this->newPassword),
            'email_verified_at' => now(),
        ]);

        if ($this->newRole !== '') {
            $user->assignRole($this->newRole);
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
        $this->editRole = $user->roles->first()->name ?? ''; 
        $this->editPassword = ''; // Sengaja dikosongkan
        $this->isEditModalOpen = true;
    }

    public function updateUser()
    {
        // Validasi, pastikan email unique kecuali untuk user ini sendiri
        $this->validate([
            'editName' => 'required|string|max:255',
            'editEmail' => 'required|email|unique:users,email,' . $this->selectedUser->id,
            'editPassword' => 'nullable|min:8', // Nullable karena opsional
        ]);

        // Update data utama
        $this->selectedUser->name = $this->editName;
        $this->selectedUser->email = $this->editEmail;
        
        // Update password HANYA jika form password diisi
        if (!empty($this->editPassword)) {
            $this->selectedUser->password = bcrypt($this->editPassword);
        }
        
        $this->selectedUser->save();

        // Update Role
        if ($this->editRole === '') {
            $this->selectedUser->syncRoles([]);
        } else {
            $this->selectedUser->syncRoles([$this->editRole]);
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

    // ==========================================
    // FUNGSI: READ (MENAMPILKAN DATA)
    // ==========================================
    public function with(): array
    {
        return [
            'users' => User::with('roles')
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('email', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10),
            'availableRoles' => Role::all(), 
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Kelola Pengguna</h1>
            <p class="text-sm text-theme-muted mt-1">Manajemen Dosen, Tendik, dan Hak Akses Sistem.</p>
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
            
            <!-- TAMBAHKAN autocomplete="off" DI SINI -->
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
            <table class="w-full text-left border-collapse">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Pengguna</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Peran (Role)</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($users as $user)
                        <tr class="hover:bg-theme-body/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img class="w-10 h-10 rounded-full object-cover border border-theme-border" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&color=059669&background=ECFDF5&bold=true" alt="">
                                    <div>
                                        <div class="text-sm font-bold text-theme-text">{{ $user->name }}</div>
                                        <div class="text-xs text-theme-muted">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
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
                            <td class="px-6 py-4 text-right space-x-2">
                                <!-- TOMBOL EDIT -->
                                <button wire:click="openEditModal({{ $user->id }})" class="text-theme-muted hover:text-primary transition-colors p-2 rounded-lg hover:bg-theme-body border border-transparent hover:border-theme-border" title="Edit Pengguna">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>

                                <!-- TOMBOL HAPUS -->
                                <button wire:click="confirmDelete({{ $user->id }})" class="text-theme-muted hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/20" title="Hapus Pengguna">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-10 text-center text-theme-muted">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30">
                    <h3 class="text-lg font-bold text-theme-text">Tambah Pengguna Baru</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Nama Lengkap</label>
                        <input type="text" wire:model="newName" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                        @error('newName') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Alamat Email</label>
                        <input type="email" wire:model="newEmail" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                        @error('newEmail') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Password Sementara</label>
                        <input type="password" wire:model="newPassword" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                        @error('newPassword') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Peran (Role)</label>
                        <select wire:model="newRole" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                            <option value="">-- Tanpa Role --</option>
                            @foreach($availableRoles as $role)
                                <option value="{{ $role->name }}">{{ strtoupper($role->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/30 flex justify-end gap-3">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30">
                    <h3 class="text-lg font-bold text-theme-text">Edit Pengguna</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Nama Lengkap</label>
                        <input type="text" wire:model="editName" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                        @error('editName') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Alamat Email</label>
                        <input type="email" wire:model="editEmail" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                        @error('editEmail') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Password Baru <span class="text-[10px] text-theme-muted normal-case font-normal">(Kosongkan jika tidak ingin diubah)</span></label>
                        <input type="password" wire:model="editPassword" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="Masukkan password baru">
                        @error('editPassword') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Peran (Role)</label>
                        <select wire:model="editRole" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                            <option value="">-- Tanpa Role --</option>
                            @foreach($availableRoles as $role)
                                <option value="{{ $role->name }}">{{ strtoupper($role->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/30 flex justify-end gap-3">
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