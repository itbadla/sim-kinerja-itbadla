<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Position;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] class extends Component {
    // ==========================================
    // STATE: MODAL TAMBAH (CREATE)
    // ==========================================
    public $isCreateModalOpen = false;
    public $newRoleName = '';
    public $newRolePermissions = []; // Array untuk menampung izin yang dicentang

    // ==========================================
    // STATE: MODAL EDIT (UPDATE)
    // ==========================================
    public $isEditModalOpen = false;
    public ?Role $selectedRole = null;
    public $editRoleName = '';
    public $editRolePermissions = [];
    public $isRoleLinked = false; // Penanda apakah role ini terikat dengan jabatan

    // ==========================================
    // STATE: MODAL HAPUS (DELETE)
    // ==========================================
    public $isDeleteModalOpen = false;
    public ?int $roleToDeleteId = null;
    public $roleToDeleteName = '';

    // ==========================================
    // FUNGSI: CREATE (TAMBAH ROLE)
    // ==========================================
    public function openCreateModal()
    {
        $this->reset(['newRoleName', 'newRolePermissions']);
        $this->resetValidation(); 
        $this->isCreateModalOpen = true;
    }

    public function saveNewRole()
    {
        $this->validate([
            'newRoleName' => 'required|string|max:255|unique:roles,name',
        ], [
            'newRoleName.required' => 'Nama Role wajib diisi.',
            'newRoleName.unique' => 'Nama Role ini sudah ada.',
        ]);

        // Buat Role tanpa strtolower agar huruf besar/kecil menyesuaikan dengan Master Jabatan
        $role = Role::create(['name' => $this->newRoleName]);

        // Pasangkan Permission yang dicentang
        if (!empty($this->newRolePermissions)) {
            $role->syncPermissions($this->newRolePermissions);
        }

        $this->isCreateModalOpen = false;
        session()->flash('message', 'Peran manual berhasil ditambahkan.');
    }

    // ==========================================
    // FUNGSI: UPDATE (EDIT ROLE)
    // ==========================================
    public function openEditModal(Role $role)
    {
        $this->resetValidation();
        $this->selectedRole = $role;
        $this->editRoleName = $role->name;
        
        // Cek apakah role ini terikat di tabel positions
        $this->isRoleLinked = Position::where('role_id', $role->id)->exists();
        
        // Ambil permission yang sudah dimiliki role ini dan masukkan ke array agar tercentang
        $this->editRolePermissions = $role->permissions->pluck('name')->toArray();
        
        $this->isEditModalOpen = true;
    }

    public function updateRole()
    {
        $this->validate([
            'editRoleName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($this->selectedRole->id),
            ],
        ], [
            'editRoleName.required' => 'Nama Role wajib diisi.',
            'editRoleName.unique' => 'Nama Role ini sudah ada.',
        ]);

        // Cegah pengubahan nama role Super Admin DAN Role bawaan Jabatan
        if ($this->selectedRole->name !== 'Super Admin' && !$this->isRoleLinked) {
            $this->selectedRole->name = $this->editRoleName;
            $this->selectedRole->save();
        }

        // Sinkronisasi permission baru (selalu diizinkan untuk diedit)
        $this->selectedRole->syncPermissions($this->editRolePermissions);
        
        $this->isEditModalOpen = false;
        session()->flash('message', 'Hak akses peran berhasil diperbarui.');
    }

    // ==========================================
    // FUNGSI: DELETE (HAPUS ROLE)
    // ==========================================
    public function confirmDelete(Role $role)
    {
        // Proteksi ekstra: Jangan biarkan Super Admin dihapus
        if ($role->name === 'Super Admin') {
            return; 
        }

        // Proteksi ekstra: Jangan biarkan role yang terikat jabatan dihapus manual
        $isLinkedToPosition = Position::where('role_id', $role->id)->exists();
        if ($isLinkedToPosition) {
            session()->flash('error', 'Role ini terikat dengan Master Jabatan. Anda hanya dapat menghapusnya melalui menu Master Jabatan.');
            return;
        }

        $this->roleToDeleteId = $role->id;
        $this->roleToDeleteName = $role->name;
        $this->isDeleteModalOpen = true;
    }

    public function deleteRole()
    {
        if ($this->roleToDeleteId) {
            Role::findOrFail($this->roleToDeleteId)->delete();
        }
        
        $this->isDeleteModalOpen = false;
        $this->reset(['roleToDeleteId', 'roleToDeleteName']);
        session()->flash('message', 'Peran manual berhasil dihapus.');
    }

    // ==========================================
    // FUNGSI: READ (MENAMPILKAN DATA)
    // ==========================================
    public function with(): array
    {
        return [
            // Mengambil semua role beserta permissionnya
            'roles' => Role::with('permissions')->orderBy('id')->get(),
            // Mengambil semua daftar izin yang tersedia di sistem
            'allPermissions' => Permission::all(),
            // Mengambil semua role_id yang terikat dengan tabel positions untuk deteksi UI
            'linkedRoleIds' => Position::whereNotNull('role_id')->pluck('role_id')->toArray(),
        ];
    }
}; ?>

<div class="space-y-6 relative py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Peran & Hak Akses</h1>
            <p class="text-sm text-theme-muted mt-1">Kelola kelompok pengguna dan menu apa saja yang boleh mereka akses.</p>
        </div>
        
        <button wire:click="openCreateModal" class="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Peran Manual
        </button>
    </div>

    <!-- Feedback Message -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center justify-between shadow-sm">
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl flex items-center justify-between shadow-sm">
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Tabel Data (READ) -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-1/4">Nama Peran</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Hak Akses (Permissions)</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($roles as $role)
                        <tr class="hover:bg-theme-body/30 transition-colors">
                            <td class="px-6 py-4 align-top">
                                <div class="inline-flex items-center gap-2">
                                    @if($role->name === 'Super Admin')
                                        <svg class="w-5 h-5 text-accent" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    @endif
                                    <span class="text-sm font-bold text-theme-text uppercase tracking-wider">{{ $role->name }}</span>
                                    
                                    <!-- Indikator Role Bawaan Jabatan -->
                                    @if(in_array($role->id, $linkedRoleIds))
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-blue-50 text-blue-600 border border-blue-200" title="Dikelola oleh Master Jabatan">Jabatan</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @if($role->name === 'Super Admin')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-accent/10 text-accent border border-accent/20">
                                            Akses Penuh (All Permissions)
                                        </span>
                                    @else
                                        @forelse($role->permissions as $permission)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-theme-body border border-theme-border text-theme-muted">
                                                {{ str_replace('-', ' ', $permission->name) }}
                                            </span>
                                        @empty
                                            <span class="text-xs italic text-theme-muted">Belum ada izin yang diberikan</span>
                                        @endforelse
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2 align-top">
                                <!-- TOMBOL EDIT -->
                                <button wire:click="openEditModal({{ $role->id }})" class="text-theme-muted hover:text-primary transition-colors p-2 rounded-lg hover:bg-theme-body border border-transparent hover:border-theme-border" title="Atur Hak Akses">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>

                                <!-- TOMBOL HAPUS (Dinonaktifkan jika Bawaan Jabatan) -->
                                @if($role->name !== 'Super Admin')
                                    @if(in_array($role->id, $linkedRoleIds))
                                        <button type="button" disabled class="text-theme-muted opacity-30 cursor-not-allowed transition-colors p-2 rounded-lg border border-transparent" title="Peran bawaan jabatan tidak dapat dihapus di sini">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @else
                                        <button wire:click="confirmDelete({{ $role->id }})" class="text-theme-muted hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/20" title="Hapus Role Manual">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-10 text-center text-theme-muted">
                                Tidak ada role yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL CREATE (TAMBAH ROLE) -->
    <!-- ========================================== -->
    @if($isCreateModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30">
                    <h3 class="text-lg font-bold text-theme-text">Tambah Peran Manual Baru</h3>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Nama Peran</label>
                        <input type="text" wire:model="newRoleName" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text placeholder-theme-muted/50" placeholder="Contoh: Panitia PMB">
                        @error('newRoleName') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        <p class="text-[10px] text-theme-muted mt-1.5">Catatan: Anda tidak perlu membuat Peran Struktural (seperti Kaprodi/Dekan) di sini karena akan otomatis dibuat dari menu Master Jabatan.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-3">Hak Akses (Izin)</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 bg-theme-body p-4 rounded-xl border border-theme-border h-48 overflow-y-auto custom-scrollbar">
                            @foreach($allPermissions as $permission)
                                <label class="flex items-start gap-2.5 cursor-pointer group">
                                    <input type="checkbox" wire:model="newRolePermissions" value="{{ $permission->name }}" class="mt-0.5 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface group-hover:border-primary transition-colors">
                                    <span class="text-sm font-medium text-theme-text group-hover:text-primary transition-colors">
                                        {{ str_replace('-', ' ', $permission->name) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/30 flex justify-end gap-3">
                    <button wire:click="$set('isCreateModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                    <button wire:click="saveNewRole" class="px-4 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all">Simpan Role</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL UPDATE (EDIT ROLE) -->
    <!-- ========================================== -->
    @if($isEditModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-theme-text/20 backdrop-blur-sm transition-opacity px-4">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-theme-text">Edit Peran & Hak Akses</h3>
                    @if($selectedRole->name === 'Super Admin')
                        <span class="px-2.5 py-1 bg-accent/10 text-accent text-xs font-bold rounded-md">Protected</span>
                    @elseif($isRoleLinked)
                        <span class="px-2.5 py-1 bg-blue-50 text-blue-600 text-xs font-bold rounded-md">Jabatan Terikat</span>
                    @endif
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Nama Peran</label>
                        <input type="text" wire:model="editRoleName" {{ $selectedRole->name === 'Super Admin' || $isRoleLinked ? 'disabled' : '' }} class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text disabled:opacity-50 disabled:cursor-not-allowed">
                        @error('editRoleName') <span class="text-xs text-red-500 font-medium mt-1">{{ $message }}</span> @enderror
                        
                        @if($selectedRole->name === 'Super Admin')
                            <p class="text-[10px] text-theme-muted mt-1.5">Nama role "Super Admin" dilindungi dan tidak dapat diubah oleh sistem.</p>
                        @elseif($isRoleLinked)
                            <p class="text-[10px] text-blue-600/80 mt-1.5 font-medium">Ini adalah Role Bawaan Jabatan. Jika ingin mengubah namanya, silakan ubah nama jabatannya di menu Master Jabatan.</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-3">Hak Akses (Izin)</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 bg-theme-body p-4 rounded-xl border border-theme-border h-48 overflow-y-auto custom-scrollbar">
                            @foreach($allPermissions as $permission)
                                <label class="flex items-start gap-2.5 cursor-pointer group">
                                    <input type="checkbox" wire:model="editRolePermissions" value="{{ $permission->name }}" class="mt-0.5 rounded border-theme-border text-primary focus:ring-primary bg-theme-surface group-hover:border-primary transition-colors">
                                    <span class="text-sm font-medium text-theme-text group-hover:text-primary transition-colors">
                                        {{ str_replace('-', ' ', $permission->name) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/30 flex justify-end gap-3">
                    <button wire:click="$set('isEditModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                    <button wire:click="updateRole" class="px-4 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all">Simpan Perubahan</button>
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
                <h3 class="text-xl font-bold text-theme-text mb-2">Hapus Peran?</h3>
                <p class="text-sm text-theme-muted mb-6">Apakah Anda yakin ingin menghapus peran manual <span class="font-bold uppercase">"{{ $roleToDeleteName }}"</span>? Pengguna yang memiliki peran ini akan kehilangan hak aksesnya.</p>
                <div class="flex justify-center gap-3">
                    <button wire:click="$set('isDeleteModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-theme-muted hover:text-theme-text bg-theme-body rounded-xl border border-theme-border transition-colors w-full">
                        Batal
                    </button>
                    <button wire:click="deleteRole" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-md transition-all w-full">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>