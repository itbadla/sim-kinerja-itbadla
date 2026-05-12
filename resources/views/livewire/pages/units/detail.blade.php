<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Unit;
use App\Models\User;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    public Unit $unit;
    
    // Form Properties untuk Tambah Staf
    public $newMemberId = '';
    public $newMemberPositionId = '';

    public function mount(Unit $unit)
    {
        // Eager load data yang dibutuhkan
        $this->unit = $unit->load(['kepalaUnit', 'parent', 'users']);
    }

    public function attachMember()
    {
        $this->validate([
            'newMemberId' => 'required|exists:users,id',
            'newMemberPositionId' => 'required|exists:positions,id',
        ], [
            'newMemberId.required' => 'Pilih pengguna terlebih dahulu.',
            'newMemberPositionId.required' => 'Pilih jabatan penempatan.',
        ]);

        // Cek duplikasi
        if ($this->unit->users()->where('user_id', $this->newMemberId)->exists()) {
            $this->addError('newMemberId', 'User ini sudah terdaftar di unit ini.');
            return;
        }

        DB::transaction(function () {
            $this->unit->users()->attach($this->newMemberId, [
                'position_id' => $this->newMemberPositionId,
                'is_active' => true
            ]);

            // [CERDAS] Sinkronisasi Role Otomatis
            $user = User::find($this->newMemberId);
            if (method_exists($user, 'syncRolesFromPositions')) {
                $user->syncRolesFromPositions();
            }
        });

        $this->reset(['newMemberId', 'newMemberPositionId']);
        $this->unit->load('users');
        session()->flash('message', 'Personil berhasil ditambahkan ke unit.');
    }

    public function detachMember($userId)
    {
        DB::transaction(function () use ($userId) {
            $this->unit->users()->detach($userId);
            
            // [CERDAS] Sinkronisasi Role Otomatis setelah dicabut
            $user = User::find($userId);
            if (method_exists($user, 'syncRolesFromPositions')) {
                $user->syncRolesFromPositions();
            }
        });

        $this->unit->load('users');
        session()->flash('message', 'Personil telah dilepas dari unit.');
    }

    public function with(): array
    {
        return [
            'availableUsers' => User::orderBy('name')->get(),
            'availablePositions' => Position::orderBy('level_otoritas')->get(),
            'positionsMap' => Position::pluck('nama_jabatan', 'id'),
        ];
    }
}; ?>

<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    
    <!-- Breadcrumbs & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <nav class="flex text-sm font-medium text-theme-muted" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('admin.units.index') }}" wire:navigate class="hover:text-primary transition-colors">Unit Kerja</a></li>
                <li class="flex items-center space-x-2">
                    <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                    <span class="text-theme-text font-bold">{{ $unit->nama_unit }}</span>
                </li>
            </ol>
        </nav>
        <a href="{{ route('admin.units.index') }}" wire:navigate class="text-xs font-bold text-theme-muted hover:text-theme-text flex items-center gap-1 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Unit
        </a>
    </div>

    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center justify-between shadow-sm">
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- KOLOM KIRI: INFO UNIT -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-theme-surface border border-theme-border rounded-2xl p-6 shadow-sm">
                <div class="w-16 h-16 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold text-2xl mb-4 shadow-inner">
                    {{ substr($unit->kode_unit, 0, 1) }}
                </div>
                <h2 class="text-xl font-black text-theme-text uppercase leading-tight">{{ $unit->nama_unit }}</h2>
                <p class="text-sm text-primary font-bold mt-1 tracking-widest">{{ $unit->kode_unit }}</p>
                
                <div class="mt-6 pt-6 border-t border-theme-border space-y-4">
                    <div>
                        <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Pimpinan / Kepala</p>
                        <p class="text-sm font-bold text-theme-text">{{ $unit->kepalaUnit->name ?? 'Belum Ditugaskan' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Unit Induk</p>
                        <p class="text-sm font-medium text-theme-text">{{ $unit->parent->nama_unit ?? 'Root (Tertinggi)' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-theme-muted uppercase tracking-widest mb-1">Total Personil</p>
                        <p class="text-sm font-medium text-theme-text">{{ $unit->users->count() }} Orang Aktif</p>
                    </div>
                </div>
            </div>

            <!-- FORM TAMBAH STAF -->
            <div class="bg-theme-surface border border-theme-border rounded-2xl p-6 shadow-sm">
                <h3 class="text-sm font-bold text-theme-text uppercase tracking-wider mb-4">Tambah Personil Baru</h3>
                <form wire:submit.prevent="attachMember" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Pilih Pengguna</label>
                        <select wire:model="newMemberId" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            <option value="">-- Pilih User --</option>
                            @foreach($availableUsers as $au)
                                <option value="{{ $au->id }}">{{ $au->name }}</option>
                            @endforeach
                        </select>
                        @error('newMemberId') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-theme-muted uppercase mb-1">Penempatan Jabatan</label>
                        <select wire:model="newMemberPositionId" class="w-full border-theme-border rounded-xl text-sm focus:ring-primary bg-theme-body/30">
                            <option value="">-- Pilih Jabatan --</option>
                            @foreach($availablePositions as $pos)
                                <option value="{{ $pos->id }}">{{ $pos->nama_jabatan }}</option>
                            @endforeach
                        </select>
                        @error('newMemberPositionId') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 hover:bg-primary-hover transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Tambahkan ke Unit
                    </button>
                </form>
            </div>
        </div>

        <!-- KOLOM KANAN: DAFTAR STAF -->
        <div class="lg:col-span-2">
            <div class="bg-theme-surface border border-theme-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/30 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-theme-text uppercase tracking-wider">Daftar Personil Aktif</h3>
                    <span class="px-2.5 py-1 rounded-lg bg-primary/10 text-primary text-[10px] font-bold border border-primary/20">{{ $unit->users->count() }} Anggota</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-theme-body/20 border-b border-theme-border">
                            <tr>
                                <th class="px-6 py-3 text-[10px] font-bold text-theme-muted uppercase tracking-widest">Nama Personil</th>
                                <th class="px-6 py-3 text-[10px] font-bold text-theme-muted uppercase tracking-widest">Jabatan di Unit</th>
                                <th class="px-6 py-3 text-[10px] font-bold text-theme-muted uppercase tracking-widest text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            @forelse($unit->users as $member)
                                <tr wire:key="member-{{ $member->id }}" class="hover:bg-theme-body/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-theme-body border border-theme-border flex items-center justify-center font-bold text-primary text-xs">
                                                {{ substr($member->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-theme-text">{{ $member->name }}</p>
                                                <p class="text-[10px] text-theme-muted">{{ $member->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 rounded bg-theme-body text-theme-text text-[10px] font-bold border border-theme-border uppercase tracking-tight">
                                            {{ $positionsMap[$member->pivot->position_id] ?? 'Anggota' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button 
                                            onclick="confirm('Yakin ingin melepas personil ini dari unit?') || event.stopImmediatePropagation()"
                                            wire:click="detachMember({{ $member->id }})"
                                            class="p-2 text-theme-muted hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                            title="Lepas dari Unit"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center text-theme-muted italic text-sm">
                                        Belum ada personil yang ditugaskan di unit ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Catatan Keamanan -->
            <div class="mt-4 p-4 bg-blue-50 border border-blue-100 rounded-xl flex gap-3">
                <svg class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="text-[11px] text-blue-700 leading-relaxed">
                    <strong>Sinkronisasi Otomatis:</strong> Setiap kali Anda menambahkan personil dengan jabatan tertentu, sistem akan otomatis memperbarui Peran (Role) pengguna tersebut agar sesuai dengan hak akses jabatannya. Perubahan ini berlaku secara real-time.
                </p>
            </div>
        </div>
    </div>
</div>