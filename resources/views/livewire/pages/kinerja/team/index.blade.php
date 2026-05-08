<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'members' => User::with(['units', 'roles'])
                ->bawahan(auth()->user())
                ->where(function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                })
                ->paginate(12),
        ];
    }
}; ?>

<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Team Saya</h1>
            <p class="text-sm text-theme-muted mt-1">Daftar staf dan dosen yang berada di bawah koordinasi unit Anda.</p>
        </div>

        <div class="relative w-full sm:w-64">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live="search" class="block w-full pl-9 pr-3 py-2 border border-theme-border bg-theme-surface rounded-xl focus:ring-primary text-sm transition-all" placeholder="Cari anggota team...">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($members as $member)
            <div wire:key="team-{{ $member->id }}" class="bg-theme-surface border border-theme-border rounded-2xl p-5 shadow-sm hover:border-primary/30 transition-all group">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-lg border border-primary/20 shrink-0 uppercase">
                        {{ substr($member->name, 0, 1) }}
                    </div>
                    
                    <div class="flex-1 overflow-hidden">
                        <h3 class="text-sm font-bold text-theme-text truncate group-hover:text-primary transition-colors">{{ $member->name }}</h3>
                        <p class="text-[11px] text-theme-muted truncate mb-3">{{ $member->email }}</p>
                        
                        <div class="space-y-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach($member->units as $unit)
                                    <span class="px-2 py-0.5 bg-theme-body border border-theme-border text-theme-text text-[9px] font-bold rounded-md uppercase tracking-tighter">
                                        {{ $unit->kode_unit ?? $unit->nama_unit }}
                                    </span>
                                @endforeach
                            </div>
                            
                            <div class="flex flex-wrap gap-1">
                                @foreach($member->roles as $role)
                                    <span class="text-[9px] font-bold text-primary uppercase">
                                        #{{ $role->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-theme-border flex items-center justify-between">
                    <div class="flex items-center gap-1 text-[10px] font-bold text-theme-muted uppercase">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Aktif
                    </div>
                    
                    <a href="#" class="text-[10px] font-bold text-primary hover:underline uppercase tracking-widest">
                        Lihat Logbook →
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-20 text-center bg-theme-surface rounded-3xl border-2 border-dashed border-theme-border">
                <p class="text-theme-muted font-medium">Tidak ada anggota team yang ditemukan.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $members->links() }}
    </div>
</div>