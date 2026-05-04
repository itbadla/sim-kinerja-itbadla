<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Unit;
use App\Models\User;

new #[Layout('layouts.app')] class extends Component {
    public Unit $unit;
    public $searchStaff = ''; 
    
    public $isAddModalOpen = false;
    public $searchAvailable = ''; 
    public $selectedUsers = []; 

    public function mount(Unit $unit)
    {
        $this->unit = $unit->load(['members', 'kepalaUnit', 'parent']);
    }

    public function openAddModal()
    {
        $this->reset(['searchAvailable', 'selectedUsers']);
        $this->isAddModalOpen = true;
    }

    public function addStaff()
    {
        $this->validate([
            'selectedUsers' => 'required|array|min:1',
        ]);

        $this->unit->members()->attach($this->selectedUsers);
        
        $this->unit->load('members');
        $this->isAddModalOpen = false;
        $this->reset('selectedUsers');
    }

    public function removeStaff($userId)
    {
        $this->unit->members()->detach($userId);
        $this->unit->load('members');
    }

    public function with(): array
    {
        return [
            'members' => $this->unit->members()
                ->where('name', 'like', '%' . $this->searchStaff . '%')
                ->orderBy('name')
                ->get(),

            'availableUsers' => User::whereDoesntHave('assignedUnits', function($query) {
                    $query->where('units.id', $this->unit->id);
                })
                ->where(function($query) {
                    $query->where('name', 'like', '%' . $this->searchAvailable . '%')
                          ->orWhere('email', 'like', '%' . $this->searchAvailable . '%');
                })
                ->orderBy('name')
                ->limit(10)
                ->get()
        ];
    }
}; ?>

{{-- PEMBUNGKUS UTAMA (ROOT ELEMENT) --}}
<div>
    <div class="space-y-6">
        <!-- Header Halaman -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.units.index') }}" wire:navigate class="p-2.5 bg-theme-surface border border-theme-border rounded-xl text-theme-muted hover:text-primary transition-all shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-theme-text tracking-tight uppercase">{{ $unit->nama_unit }}</h1>
                    <nav class="flex text-xs font-bold text-theme-muted uppercase tracking-widest mt-1">
                        <span>{{ $unit->kode_unit ?: 'Tanpa Kode' }}</span>
                        <span class="mx-2">/</span>
                        <span class="text-primary">{{ $unit->parent->nama_unit ?? 'Top Level Unit' }}</span>
                    </nav>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-4 py-2 bg-theme-surface border border-theme-border rounded-xl text-xs font-bold text-theme-text shadow-sm">
                    Total Staff: {{ $unit->members->count() }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- KOLOM KIRI: Informasi & Pejabat -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-theme-border bg-theme-body/30">
                        <h3 class="text-xs font-bold text-theme-muted uppercase tracking-widest">Kepala Unit / Pejabat</h3>
                    </div>
                    <div class="p-6">
                        @if($unit->kepalaUnit)
                            <div class="flex flex-col items-center text-center">
                                <div class="w-20 h-20 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl font-bold border border-primary/20 mb-4 shadow-inner">
                                    {{ substr($unit->kepalaUnit->name, 0, 1) }}
                                </div>
                                <h4 class="text-lg font-extrabold text-theme-text">{{ $unit->kepalaUnit->name }}</h4>
                                <p class="text-sm text-theme-muted">{{ $unit->kepalaUnit->email }}</p>
                                <div class="mt-4 w-full pt-4 border-t border-theme-border">
                                    <span class="px-3 py-1.5 rounded-lg bg-primary text-white text-[10px] font-bold uppercase tracking-widest shadow-lg shadow-primary/20">
                                        Penanggung Jawab
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <div class="w-16 h-16 rounded-2xl bg-red-50 dark:bg-red-500/10 text-red-500 flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                                <p class="text-sm font-bold text-red-500">Pejabat Belum Ditunjuk</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm">
                    <h3 class="text-xs font-bold text-theme-muted uppercase tracking-widest mb-4">Ringkasan Unit</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-2 border-b border-theme-border border-dashed">
                            <span class="text-xs text-theme-muted">Tingkatan</span>
                            <span class="text-xs font-bold text-theme-text uppercase">{{ $unit->parent_id ? 'Sub-Unit' : 'Unit Utama' }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-xs text-theme-muted">Dibuat Pada</span>
                            <span class="text-xs font-bold text-theme-text">{{ $unit->created_at->format('d M Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN: Manajemen Staff -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-sm overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-theme-border flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-theme-body/30">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/10 rounded-xl">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-theme-text uppercase tracking-widest">Anggota Staff Aktif</h3>
                            </div>
                            <button type="button" wire:click="openAddModal" class="ml-2 bg-primary hover:bg-primary-hover text-white p-2 rounded-xl transition-all shadow-lg flex items-center justify-center group">
                                <svg class="w-4 h-4 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            </button>
                        </div>
                        
                        <div class="relative w-full sm:w-72">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </span>
                            <input type="text" wire:model.live="searchStaff" placeholder="Cari nama staff..." class="block w-full pl-9 pr-3 py-2 border border-theme-border bg-theme-body rounded-xl text-xs text-theme-text focus:ring-primary focus:border-primary shadow-inner">
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-theme-body/50 border-b border-theme-border">
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-bold text-theme-muted uppercase tracking-widest">Nama / Identitas</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-theme-muted uppercase tracking-widest text-center">Status Kerja</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-theme-muted uppercase tracking-widest text-right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-theme-border">
                                @forelse($members as $member)
                                    <tr class="hover:bg-theme-body/30 transition-colors group" wire:key="member-{{ $member->id }}">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-2xl bg-theme-body border border-theme-border flex items-center justify-center text-xs font-bold text-primary shadow-sm group-hover:border-primary/30 transition-colors">
                                                    {{ strtoupper(substr($member->name, 0, 2)) }}
                                                </div>
                                                <div>
                                                    <div class="text-sm font-bold text-theme-text group-hover:text-primary transition-colors">{{ $member->name }}</div>
                                                    <div class="text-[10px] text-theme-muted font-medium uppercase tracking-wider">{{ $member->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="px-3 py-1 rounded-lg text-[10px] font-bold bg-theme-body border border-theme-border text-theme-text uppercase tracking-tighter shadow-sm">
                                                {{ $member->jabatan ?: 'Staff' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <button wire:click="removeStaff({{ $member->id }})" wire:confirm="Apakah Anda yakin?" class="p-2 rounded-xl text-theme-muted hover:text-red-500 hover:bg-red-50 transition-all sm:opacity-0 group-hover:opacity-100 border border-transparent hover:border-red-200">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-6 py-16 text-center text-theme-muted italic">Belum ada staff terdaftar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($isAddModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/50 backdrop-blur-sm px-4 py-6" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-2xl w-full max-w-md flex flex-col max-h-[85vh] overflow-hidden" onclick="event.stopPropagation()">
                
                <!-- Header Modal -->
                <div class="px-6 py-4 border-b border-theme-border flex justify-between items-center bg-theme-body/30">
                    <h3 class="text-sm font-extrabold text-theme-text uppercase tracking-tight">Cari & Tambah Staff</h3>
                    <button type="button" wire:click="$set('isAddModalOpen', false)" class="text-theme-muted hover:text-theme-text">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar space-y-4">
                    <!-- Input Pencarian (Gunakan .live agar daftar user terupdate otomatis) -->
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" wire:model.live="searchAvailable" placeholder="Ketik nama staff..." class="block w-full pl-9 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl text-xs text-theme-text focus:ring-primary focus:border-primary shadow-inner">
                    </div>

                    <!-- Daftar User Tersedia -->
                    <div class="space-y-1">
                        @forelse($availableUsers as $user)
                            {{-- Gunakan wire:key agar Livewire tidak bingung saat render ulang --}}
                            <label wire:key="available-user-{{ $user->id }}" class="flex items-center gap-3 p-3 hover:bg-theme-body rounded-2xl cursor-pointer transition-all border border-transparent hover:border-theme-border group">
                                {{-- PENTING: Gunakan .live agar tombol 'Tambahkan' langsung aktif saat dicentang --}}
                                <input type="checkbox" wire:model.live="selectedUsers" value="{{ (string)$user->id }}" class="w-5 h-5 rounded-lg border-theme-border text-primary focus:ring-primary bg-theme-body transition-all">
                                <div>
                                    <p class="text-sm font-bold text-theme-text group-hover:text-primary">{{ $user->name }}</p>
                                    <p class="text-[10px] text-theme-muted uppercase tracking-widest">{{ $user->email }}</p>
                                </div>
                            </label>
                        @empty
                            <div class="py-10 text-center text-xs text-theme-muted italic text-theme-muted">Tidak ditemukan staff lain.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/30 flex justify-between items-center">
                    <span class="text-[10px] font-bold text-theme-muted uppercase">{{ count($selectedUsers) }} dipilih</span>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('isAddModalOpen', false)" class="px-4 py-2 text-xs font-bold text-theme-muted hover:text-theme-text uppercase">Batal</button>
                        
                        {{-- Perbaikan: Tombol akan aktif secara instan berkat wire:model.live di atas --}}
                        <button type="button" 
                                wire:click="addStaff" 
                                wire:loading.attr="disabled"
                                @if(count($selectedUsers) === 0) disabled @endif 
                                class="px-6 py-2 text-xs font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all uppercase">
                            <span wire:loading.remove>Tambahkan</span>
                            <span wire:loading>Memproses...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>