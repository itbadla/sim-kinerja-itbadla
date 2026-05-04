<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\FundSubmission;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterStatus = 'menunggu_verifikasi'; // Default menampilkan yang butuh dicek

    // State Modal
    public $isModalOpen = false;
    public ?FundSubmission $selectedSubmission = null;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }

    public function openModal($id)
    {
        $this->selectedSubmission = FundSubmission::with(['user', 'unit'])->findOrFail($id);
        $this->isModalOpen = true;
    }

    public function approveLpj()
    {
        if ($this->selectedSubmission) {
            $this->selectedSubmission->update([
                'status_lpj' => 'selesai'
            ]);
            session()->flash('success', 'LPJ berhasil diverifikasi dan disetujui.');
            $this->isModalOpen = false;
        }
    }

    public function rejectLpj()
    {
        if ($this->selectedSubmission) {
            // Jika ditolak, kembalikan status ke 'belum' agar user bisa upload ulang
            $this->selectedSubmission->update([
                'status_lpj' => 'belum',
                'catatan_verifikator' => 'LPJ Ditolak/Revisi: Mohon perbaiki dan unggah ulang bukti kuitansi yang valid sesuai catatan pemeriksaan.'
            ]);
            session()->flash('error', 'LPJ dikembalikan ke pengaju untuk direvisi.');
            $this->isModalOpen = false;
        }
    }

    public function with(): array
    {
        $query = FundSubmission::with(['user', 'unit'])
            ->where('status', 'approved') // Hanya yang dananya sudah cair
            ->whereIn('status_lpj', ['menunggu_verifikasi', 'selesai']); // Yang belum lapor tidak usah ditampilkan di sini

        if ($this->filterStatus) {
            $query->where('status_lpj', $this->filterStatus);
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
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Verifikasi LPJ</h1>
            <p class="text-sm text-theme-muted mt-1">Periksa kesesuaian nota/kuitansi dengan nominal realisasi yang dilaporkan.</p>
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
        <select wire:model.live="filterStatus" class="w-full md:w-48 border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary">
            <option value="menunggu_verifikasi">Perlu Dicek</option>
            <option value="selesai">Sudah Selesai (Clear)</option>
            <option value="">Semua Data LPJ</option>
        </select>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama pengaju..." class="w-full pl-4 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl text-sm focus:ring-primary">
    </div>

    <!-- Table -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-48">Pengaju</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Detail & Realisasi</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center w-36">Bukti</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($submissions as $item)
                        <tr class="hover:bg-theme-body/30">
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm font-bold text-theme-text">{{ $item->user->name ?? 'Unknown' }}</div>
                                <div class="text-[10px] text-theme-muted">{{ $item->unit->nama_unit ?? 'Pribadi' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <p class="text-sm font-medium text-theme-text mb-1">{{ $item->keperluan }}</p>
                                <div class="flex items-center gap-4 text-xs">
                                    <span class="text-theme-muted">Dana: <strong class="text-theme-text">Rp {{ number_format($item->nominal, 0, ',', '.') }}</strong></span>
                                    <span class="text-theme-muted">Realisasi: <strong class="text-blue-600">Rp {{ number_format($item->nominal_realisasi, 0, ',', '.') }}</strong></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                @if($item->file_lpj)
                                    <a href="{{ Storage::url($item->file_lpj) }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-primary hover:text-primary-hover bg-primary/10 px-2.5 py-1 rounded-md">
                                        Lihat Kuitansi
                                    </a>
                                @else
                                    <span class="text-[10px] text-theme-muted italic">Tidak ada file</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-right">
                                @if($item->status_lpj === 'menunggu_verifikasi')
                                    <button wire:click="openModal({{ $item->id }})" class="inline-flex items-center px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-hover shadow-sm shadow-primary/20">
                                        Periksa
                                    </button>
                                @else
                                    <!-- Tombol untuk data yang sudah selesai (Bisa diedit ulang) -->
                                    <button wire:click="openModal({{ $item->id }})" class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-500 text-white text-xs font-bold rounded-lg hover:bg-amber-600 shadow-sm shadow-amber-500/20" title="Buka kembali untuk direvisi">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        Edit Ulang
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-theme-muted">Belum ada LPJ yang perlu diverifikasi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($submissions->hasPages()) <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">{{ $submissions->links() }}</div> @endif
    </div>

    <!-- Modal Verifikasi -->
    @if($isModalOpen && $selectedSubmission)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-theme-text">Cek Kesesuaian Kuitansi</h3>
                    @if($selectedSubmission->status_lpj === 'selesai')
                        <span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase">Status Saat Ini: Selesai</span>
                    @endif
                </div>
                
                <div class="p-6 space-y-4">
                    <!-- Pesan Peringatan jika membuka data yang sudah selesai -->
                    @if($selectedSubmission->status_lpj === 'selesai')
                        <div class="bg-amber-50 p-3 rounded-xl border border-amber-200">
                            <p class="text-xs text-amber-700 font-medium flex items-start gap-2">
                                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                <span>LPJ ini sudah dikunci sebagai <strong>Selesai</strong>. Jika ditemukan kejanggalan, Anda dapat membatalkannya dengan menekan tombol <strong>Tolak / Revisi</strong> untuk meminta pengaju mengunggah ulang bukti.</span>
                            </p>
                        </div>
                    @endif

                    <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                        <p class="text-xs text-blue-600 font-bold mb-1">Nominal Dilaporkan (Realisasi):</p>
                        <p class="text-2xl font-extrabold text-theme-text">Rp {{ number_format($selectedSubmission->nominal_realisasi, 0, ',', '.') }}</p>
                    </div>

                    <div class="text-center py-4">
                        <p class="text-sm text-theme-text mb-4">Apakah bukti kuitansi yang dilampirkan sudah sesuai dengan nominal di atas dan memenuhi standar keuangan?</p>
                        @if($selectedSubmission->file_lpj)
                            <a href="{{ Storage::url($selectedSubmission->file_lpj) }}" target="_blank" class="text-primary hover:underline font-bold text-sm"> Buka File Bukti Kuitansi (Tab Baru)</a>
                        @endif
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-between gap-3">
                    <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">Batal</button>
                    
                    <div class="flex gap-2">
                        <button type="button" wire:click="rejectLpj" class="px-4 py-2 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-md transition-all">
                            {{ $selectedSubmission->status_lpj === 'selesai' ? 'Batalkan & Minta Revisi' : 'Tolak / Revisi' }}
                        </button>
                        <button type="button" wire:click="approveLpj" class="px-4 py-2 text-sm font-bold text-white bg-emerald-500 hover:bg-emerald-600 rounded-xl shadow-md transition-all">
                            {{ $selectedSubmission->status_lpj === 'selesai' ? 'Tetap Sesuai (Simpan)' : 'Sesuai & Selesai' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>