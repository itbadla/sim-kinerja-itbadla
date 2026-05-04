<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\FundSubmission;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterStatus = 'belum'; // Default: Menampilkan yang menunggak

    // State Modal
    public $isModalOpen = false;
    public ?FundSubmission $selectedSubmission = null;
    public $catatan_pengembalian = '';

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }

    public function openModal($id)
    {
        $this->selectedSubmission = FundSubmission::with(['user', 'unit'])->findOrFail($id);
        $this->catatan_pengembalian = $this->selectedSubmission->catatan_pengembalian ?? '';
        $this->isModalOpen = true;
    }

    public function markAsRefunded()
    {
        if ($this->selectedSubmission) {
            $this->selectedSubmission->update([
                'waktu_pengembalian' => now(), // Set waktu saat tombol ditekan
                'catatan_pengembalian' => $this->catatan_pengembalian,
            ]);
            session()->flash('success', 'Pengembalian dana berhasil dicatat sebagai Lunas.');
            $this->isModalOpen = false;
        }
    }

    public function cancelRefund()
    {
        if ($this->selectedSubmission) {
            $this->selectedSubmission->update([
                'waktu_pengembalian' => null, // Reset menjadi belum lunas
                'catatan_pengembalian' => null,
            ]);
            session()->flash('error', 'Status pengembalian dibatalkan (kembali berstatus menunggak).');
            $this->isModalOpen = false;
        }
    }

    public function with(): array
    {
        // LOGIKA UTAMA: Ambil LPJ Selesai yang ADA SELISIH SISA (Nominal Cair > Realisasi)
        $query = FundSubmission::with(['user', 'unit'])
            ->where('status', 'approved')
            ->where('status_lpj', 'selesai')
            ->whereColumn('nominal', '>', 'nominal_realisasi');

        // Filter Lunas / Belum
        if ($this->filterStatus === 'belum') {
            $query->whereNull('waktu_pengembalian');
        } elseif ($this->filterStatus === 'lunas') {
            $query->whereNotNull('waktu_pengembalian');
        }

        if ($this->search) {
            $query->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })->orWhere('keperluan', 'like', '%' . $this->search . '%');
        }

        return [
            'submissions' => $query->latest('updated_at')->paginate(10),
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Verifikasi Pengembalian Dana</h1>
            <p class="text-sm text-theme-muted mt-1">Pantau sisa dana (selisih) dari LPJ yang harus dikembalikan ke institusi.</p>
        </div>
    </div>

    <!-- Alert Messages -->
    @if (session()->has('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-600 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-amber-50 border border-amber-200 text-amber-600 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- Filter & Search -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border flex flex-col md:flex-row gap-3">
        <select wire:model.live="filterStatus" class="w-full md:w-56 border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary">
            <option value="belum">Belum Dikembalikan (Menunggak)</option>
            <option value="lunas">Sudah Dikembalikan (Lunas)</option>
            <option value="">Semua Data Selisih</option>
        </select>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama pengaju atau keperluan..." class="w-full pl-4 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl text-sm focus:ring-primary">
    </div>

    <!-- Table -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-48">Pengaju</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Detail & LPJ</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Sisa Kembalian (Rp)</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center w-36">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($submissions as $item)
                        @php $selisih = $item->nominal - $item->nominal_realisasi; @endphp
                        <tr class="hover:bg-theme-body/30">
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm font-bold text-theme-text">{{ $item->user->name ?? 'Unknown' }}</div>
                                <div class="text-[10px] text-theme-muted">{{ $item->unit->nama_unit ?? 'Pribadi' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <p class="text-sm font-medium text-theme-text mb-1">{{ $item->keperluan }}</p>
                                <div class="text-[10px] text-theme-muted">
                                    Cair: Rp {{ number_format($item->nominal, 0, ',', '.') }} | 
                                    Terpakai: Rp {{ number_format($item->nominal_realisasi, 0, ',', '.') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top text-right">
                                <div class="text-sm font-extrabold text-red-500">Rp {{ number_format($selisih, 0, ',', '.') }}</div>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                @if($item->waktu_pengembalian)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200">
                                        Lunas
                                    </span>
                                    <div class="text-[9px] text-theme-muted mt-1">{{ $item->waktu_pengembalian->format('d M Y') }}</div>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200">
                                        Menunggak
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-right">
                                <button wire:click="openModal({{ $item->id }})" class="inline-flex items-center px-3 py-1.5 bg-theme-body border border-theme-border text-theme-text text-xs font-bold rounded-lg hover:bg-theme-surface hover:text-primary transition-colors shadow-sm">
                                    Kelola
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center">
                                <h4 class="text-sm font-bold text-theme-text">Tidak ada sisa dana.</h4>
                                <p class="text-xs text-theme-muted mt-1">Semua LPJ saat ini memiliki nominal yang pas atau minus.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($submissions->hasPages()) <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">{{ $submissions->links() }}</div> @endif
    </div>

    <!-- Modal Form Pencatatan -->
    @if($isModalOpen && $selectedSubmission)
        @php $sisaDana = $selectedSubmission->nominal - $selectedSubmission->nominal_realisasi; @endphp
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50">
                    <h3 class="text-lg font-bold text-theme-text">Kelola Status Pengembalian</h3>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="bg-red-50 p-4 rounded-xl border border-red-200 text-center">
                        <p class="text-xs text-red-600 font-bold mb-1">Nominal yang Harus Dikembalikan:</p>
                        <p class="text-3xl font-extrabold text-red-600">Rp {{ number_format($sisaDana, 0, ',', '.') }}</p>
                        <p class="text-[10px] text-red-500 mt-2">Dari: {{ $selectedSubmission->user->name ?? '-' }} ({{ $selectedSubmission->keperluan }})</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-theme-muted uppercase tracking-wider mb-1.5">Catatan Pembayaran / Bukti (Opsional)</label>
                        <input type="text" wire:model="catatan_pengembalian" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="Misal: Ditransfer ke BCA tgl 12, atau Diserahkan tunai ke Budi.">
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-between gap-3">
                    <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Tutup</button>
                    
                    <div class="flex gap-2">
                        @if($selectedSubmission->waktu_pengembalian)
                            <button type="button" wire:click="cancelRefund" class="px-4 py-2 text-sm font-bold text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-xl border border-amber-200 transition-all">
                                Batalkan (Set Belum Lunas)
                            </button>
                            <button type="button" wire:click="markAsRefunded" class="px-4 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all">
                                Update Catatan
                            </button>
                        @else
                            <button type="button" wire:click="markAsRefunded" class="px-4 py-2 text-sm font-bold text-white bg-emerald-500 hover:bg-emerald-600 rounded-xl shadow-md transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Tandai Lunas
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>