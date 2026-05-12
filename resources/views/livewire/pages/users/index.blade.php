<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Unit;
use App\Models\Position;
use Spatie\Permission\Models\Role;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
    public $newPositionId = ''; 
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
    public $editPositionId = '';

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
        $this->reset(['newName', 'newEmail', 'newPassword', 'newRoles', 'newUnitId', 'newPositionId', 'headedUnitIds']);
        $this->resetValidation(); 
        $this->isCreateModalOpen = true;
    }

    public function saveNewUser()
    {
        // Pastikan input checkbox tetap array
        $this->newRoles = is_array($this->newRoles) ? $this->newRoles : [];
        $this->headedUnitIds = is_array($this->headedUnitIds) ? $this->headedUnitIds : [];

        $this->validate([
            'newName' => 'required|string|max:255',
            'newEmail' => 'required|email|unique:users,email',
            'newPassword' => 'required|min:8',
            'newUnitId' => 'required|exists:units,id',
            'newPositionId' => 'required|exists:positions,id',
            'headedUnitIds' => 'nullable|array',
            'newRoles' => 'nullable|array',
        ]);

        DB::transaction(function () {
            $user = User::create([
                'name' => $this->newName,
                'email' => $this->newEmail,
                'password' => bcrypt($this->newPassword),
                'email_verified_at' => now(),
            ]);

            // 1. Simpan ke Pivot Unit & Jabatan
            $user->units()->attach($this->newUnitId, [
                'position_id' => $this->newPositionId,
                'is_active' => true
            ]);

            // 2. Tentukan Role: Gabungkan Manual + Otomatis Jabatan
            $roleFromPosition = Position::find($this->newPositionId)?->nama_jabatan;
            $finalRoles = $this->newRoles;
            if ($roleFromPosition && !in_array($roleFromPosition, $finalRoles)) {
                $finalRoles[] = $roleFromPosition;
            }
            $user->syncRoles($finalRoles);

            // 3. Update Amanah Kepala Unit
            if (!empty($this->headedUnitIds)) {
                Unit::whereIn('id', $this->headedUnitIds)->update(['kepala_unit_id' => $user->id]);
            }
        });

        $this->isCreateModalOpen = false;
        session()->flash('message', 'Pengguna berhasil ditambahkan.');
    }

    // ==========================================
    // FUNGSI: UPDATE (EDIT USER & ROLE)
    // ==========================================
    public function openEditModal($id)
    {
        $this->resetValidation();
        $user = User::with(['roles', 'units'])->findOrFail($id);
        $this->selectedUser = $user;
        
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        
        $primaryUnit = $user->units->first();
        $this->editUnitId = $primaryUnit ? $primaryUnit->id : '';
        $this->editPositionId = $primaryUnit ? $primaryUnit->pivot->position_id : '';
        
        // Load roles yang saat ini dimiliki
        $this->editRoles = $user->roles->pluck('name')->toArray(); 
        $this->headedUnitIds = Unit::where('kepala_unit_id', $user->id)->pluck('id')->toArray();
        $this->editPassword = ''; 

        $this->isEditModalOpen = true;
    }

    public function updateUser()
    {
        // Fix untuk uncheck all (Livewire sends false/null if empty)
        $this->editRoles = is_array($this->editRoles) ? $this->editRoles : [];
        $this->headedUnitIds = is_array($this->headedUnitIds) ? $this->headedUnitIds : [];

        $this->validate([
            'editName' => 'required|string|max:255',
            'editEmail' => 'required|email|unique:users,email,' . $this->selectedUser->id,
            'editPassword' => 'nullable|min:8',
            'editUnitId' => 'required|exists:units,id',
            'editPositionId' => 'required|exists:positions,id',
            'headedUnitIds' => 'nullable|array',
            'editRoles' => 'nullable|array',
        ]);

        DB::transaction(function () {
            // 1. Perbarui Data Dasar
            $this->selectedUser->update([
                'name' => $this->editName,
                'email' => $this->editEmail,
            ]);
            
            if (!empty($this->editPassword)) {
                $this->selectedUser->update(['password' => bcrypt($this->editPassword)]);
            }
            
            // 2. Sinkronisasi Unit & Jabatan
            // Kita gunakan syncWithoutDetaching untuk primary unit agar tidak menghapus unit lain 
            // jika di masa depan user punya banyak penempatan.
            $this->selectedUser->units()->sync([
                $this->editUnitId => [
                    'position_id' => $this->editPositionId,
                    'is_active' => true
                ]
            ]);

            // 3. Logika Role Cerdas: Gabungkan Pilihan Manual dengan Role Jabatan
            $pos = Position::find($this->editPositionId);
            $roleFromPosition = $pos ? $pos->nama_jabatan : null;
            
            $finalRoles = $this->editRoles;
            // Jika jabatan dipilih, pastikan role jabatannya masuk ke dalam daftar
            if ($roleFromPosition && !in_array($roleFromPosition, $finalRoles)) {
                $finalRoles[] = $roleFromPosition;
            }
            
            // Simpan semua role sekaligus (mengakomodasi 2 atau lebih role)
            $this->selectedUser->syncRoles($finalRoles);

            // 4. Update Amanah Kepala Unit
            Unit::where('kepala_unit_id', $this->selectedUser->id)->update(['kepala_unit_id' => null]);
            if (!empty($this->headedUnitIds)) {
                Unit::whereIn('id', $this->headedUnitIds)->update(['kepala_unit_id' => $this->selectedUser->id]);
            }
            
            $this->selectedUser->refresh();
        });

        $this->isEditModalOpen = false;
        session()->flash('message', 'Data pengguna dan peran berhasil diperbarui.');
    }

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
        session()->flash('message', 'Pengguna berhasil dihapus.');
    }

    public function impersonate($id)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            return;
        }
        $userToImpersonate = User::findOrFail($id);
        if ($userToImpersonate->id === auth()->id()) return;
        session()->put('impersonated_by', auth()->id());
        Auth::login($userToImpersonate);
        return redirect()->to(route('dashboard'));
    }

    public function with(): array
    {
        return [
            'users' => User::with(['roles', 'units'])
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('email', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10),
            'availableRoles' => Role::all(), 
            'availableUnits' => Unit::orderBy('nama_unit')->get(),
            'availablePositions' => Position::orderBy('level_otoritas')->get(),
            'positionsMap' => Position::pluck('nama_jabatan', 'id'),
        ];
    }
}; ?>

<div class="space-y-6 relative py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto text-theme-text">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Kelola Pengguna</h1>
            <p class="text-sm text-theme-muted mt-1">Manajemen Dosen, Tendik, Unit, dan Hak Akses Sistem.</p>
        </div>
        <button wire:click="openCreateModal" class="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah User
        </button>
    </div>

    <!-- Feedback Message -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center justify-between shadow-sm">
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Kotak Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex items-center gap-3">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live="search" class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary text-sm transition-all" placeholder="Cari nama atau email...">
        </div>
    </div>

    <!-- Tabel Data -->
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
                        <tr wire:key="user-row-{{ $user->id }}" class="hover:bg-theme-body/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-bold shadow-inner uppercase text-xs">
                                        {{ substr($user->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold">{{ $user->name }}</div>
                                        <div class="text-[11px] text-theme-muted">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 align-top">
                                @php $primaryUnit = $user->units->first(); @endphp
                                @if($primaryUnit)
                                    <div class="mb-1">
                                        <span class="text-xs font-bold">{{ $primaryUnit->nama_unit }}</span>
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[8px] uppercase tracking-widest bg-theme-body border border-theme-border text-theme-muted">Homebase</span>
                                    </div>
                                    <div class="text-[10px] text-primary font-bold uppercase tracking-tight">
                                        {{ $positionsMap[$primaryUnit->pivot->position_id] ?? 'Tidak ada jabatan' }}
                                    </div>
                                @endif

                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach($availableUnits->where('kepala_unit_id', $user->id) as $hUnit)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 border border-blue-100 text-[9px] font-bold uppercase">
                                            Kepala {{ $hUnit->kode_unit ?? 'Unit' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase border {{ $role->name === 'Super Admin' ? 'bg-theme-text text-theme-surface border-theme-text text-white' : 'bg-theme-body border-theme-border' }}">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-[10px] italic text-theme-muted">No Role</span>
                                    @endforelse
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    @if($user->id !== auth()->id())
                                        <button wire:click="impersonate({{ $user->id }})" class="text-theme-muted hover:text-emerald-600 p-2 rounded-lg hover:bg-emerald-50 transition-colors" title="Login Sebagai User">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                    @endif
                                    <button wire:click="openEditModal({{ $user->id }})" class="text-theme-muted hover:text-primary p-2 rounded-lg hover:bg-theme-body transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button wire:click="confirmDelete({{ $user->id }})" class="text-theme-muted hover:text-red-500 p-2 rounded-lg hover:bg-red-50 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-theme-muted italic">Tidak ada pengguna ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">
            {{ $users->links() }}
        </div>
    </div>

    <!-- MODAL CREATE -->
    @if($isCreateModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 overflow-y-auto" x-data>
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl my-auto">
                <div class="px-6 py-4 border-b border-theme-border flex justify-between items-center bg-theme-body/50 rounded-t-2xl">
                    <h3 class="text-lg font-bold">Tambah Pengguna Baru</h3>
                    <button wire:click="$set('isCreateModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form wire:submit.prevent="saveNewUser" class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Nama Lengkap</label>
                            <input type="text" wire:model="newName" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            @error('newName') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Email Kampus</label>
                            <input type="email" wire:model="newEmail" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            @error('newEmail') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Password</label>
                            <input type="password" wire:model="newPassword" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            @error('newPassword') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-4 border-t border-theme-border">
                        <h4 class="text-xs font-bold mb-3 uppercase tracking-wider text-primary">Penempatan Utama</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Unit Homebase</label>
                                <select wire:model="newUnitId" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                                    <option value="">-- Pilih Unit --</option>
                                    @foreach($availableUnits as $unit) <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option> @endforeach
                                </select>
                                @error('newUnitId') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Jabatan Sistem</label>
                                <select wire:model="newPositionId" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                                    <option value="">-- Pilih Jabatan --</option>
                                    @foreach($availablePositions as $pos) <option value="{{ $pos->id }}">{{ $pos->nama_jabatan }}</option> @endforeach
                                </select>
                                @error('newPositionId') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold mb-2 uppercase tracking-wider text-primary">Peran Sistem Tambahan</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($availableRoles as $role)
                                <label wire:key="role-create-{{ $role->id }}" class="flex items-center gap-2 px-3 py-2 border border-theme-border rounded-xl cursor-pointer hover:bg-theme-body/50">
                                    <input type="checkbox" wire:model="newRoles" value="{{ $role->name }}" class="rounded text-primary focus:ring-primary">
                                    <span class="text-[10px] font-bold uppercase">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('newRoles') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <h4 class="text-xs font-bold mb-2 uppercase tracking-wider text-primary">Amanah Struktural (Kepala)</h4>
                        <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 bg-theme-body/20 rounded-xl border border-theme-border">
                            @foreach($availableUnits as $unit)
                                <label wire:key="headed-create-{{ $unit->id }}" class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="headedUnitIds" value="{{ $unit->id }}" class="rounded text-primary focus:ring-primary">
                                    <span class="text-[10px] font-medium">{{ $unit->kode_unit ?? $unit->nama_unit }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('headedUnitIds') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-theme-border">
                        <button type="button" wire:click="$set('isCreateModalOpen', false)" class="text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-xl font-bold text-sm shadow-lg shadow-primary/20">Simpan User</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL EDIT -->
    @if($isEditModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 overflow-y-auto" x-data>
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl my-auto">
                <div class="px-6 py-4 border-b border-theme-border flex justify-between items-center bg-theme-body/50 rounded-t-2xl">
                    <h3 class="text-lg font-bold">Edit Data Pengguna</h3>
                    <button wire:click="$set('isEditModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form wire:submit.prevent="updateUser" class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Nama Lengkap</label>
                            <input type="text" wire:model="editName" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            @error('editName') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Email Kampus</label>
                            <input type="email" wire:model="editEmail" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            @error('editEmail') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Password Baru (Opsional)</label>
                            <input type="password" wire:model="editPassword" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            @error('editPassword') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-4 border-t border-theme-border">
                        <h4 class="text-xs font-bold mb-3 uppercase tracking-wider text-primary">Penempatan Utama</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Unit Homebase</label>
                                <select wire:model="editUnitId" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                                    <option value="">-- Pilih Unit --</option>
                                    @foreach($availableUnits as $unit) <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option> @endforeach
                                </select>
                                @error('editUnitId') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Jabatan Sistem</label>
                                <select wire:model="editPositionId" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                                    <option value="">-- Pilih Jabatan --</option>
                                    @foreach($availablePositions as $pos) <option value="{{ $pos->id }}">{{ $pos->nama_jabatan }}</option> @endforeach
                                </select>
                                @error('editPositionId') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold mb-2 uppercase tracking-wider text-primary">Peran Sistem Tambahan</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($availableRoles as $role)
                                <label wire:key="role-edit-{{ $role->id }}" class="flex items-center gap-2 px-3 py-2 border border-theme-border rounded-xl cursor-pointer hover:bg-theme-body/50">
                                    <input type="checkbox" wire:model="editRoles" value="{{ $role->name }}" class="rounded text-primary focus:ring-primary">
                                    <span class="text-[10px] font-bold uppercase">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('editRoles') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <h4 class="text-xs font-bold mb-2 uppercase tracking-wider text-primary">Amanah Struktural (Kepala)</h4>
                        <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-3 bg-theme-body/20 rounded-xl border border-theme-border">
                            @foreach($availableUnits as $unit)
                                <label wire:key="headed-edit-{{ $unit->id }}" class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="headedUnitIds" value="{{ $unit->id }}" class="rounded text-primary focus:ring-primary">
                                    <span class="text-[10px] font-medium">{{ $unit->kode_unit ?? $unit->nama_unit }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('headedUnitIds') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-theme-border">
                        <button type="button" wire:click="$set('isEditModalOpen', false)" class="text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-xl font-bold text-sm shadow-lg shadow-primary/20">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- DELETE MODAL -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[110] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-2xl w-full max-w-sm p-6 text-center">
                <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-lg font-bold mb-2">Hapus Pengguna?</h3>
                <p class="text-sm text-theme-muted mb-6">Akun akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
                <div class="flex gap-3">
                    <button wire:click="$set('isDeleteModalOpen', false)" class="flex-1 py-2 text-sm font-bold text-theme-muted bg-theme-body rounded-xl border border-theme-border transition-colors">Batal</button>
                    <button wire:click="deleteUser" class="flex-1 py-2 text-sm font-bold text-white bg-red-500 rounded-xl shadow-md hover:bg-red-600 transition-colors">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>