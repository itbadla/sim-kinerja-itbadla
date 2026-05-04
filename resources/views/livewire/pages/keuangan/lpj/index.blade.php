<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\FundSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $filterStatusLpj = '';

    // ==========================================
    // STATE: MODAL FORM LPJ
    // ==========================================
    public $isModalOpen = false;
    public ?FundSubmission $selectedSubmission = null;
    
    // Field Input LPJ
    public $nominal_realisasi = '';
    public $file_lpj;
    public $file_lpj_lama;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatusLpj() { $this->resetPage(); }

    // ==========================================
    // FUNGSI: BUKA MODAL
    // ==========================================
    public function openModal($id)
    {
        $this->resetValidation();
        $this->reset(['file_lpj']);
        
        $this->selectedSubmission = FundSubmission::where('user_id', Auth::id())
            ->where('status', 'approved')
            ->findOrFail($id);
            
        // Mencegah edit jika LPJ sudah diverifikasi selesai oleh Keuangan
        if ($this->selectedSubmission->status_lpj === 'selesai') {
            session()->flash('error', 'LPJ yang sudah disetujui tidak dapat diubah.');
            return;
        }

        $this->nominal_realisasi = $this->selectedSubmission->nominal_realisasi 
                                    ? round($this->selectedSubmission->nominal_realisasi) 
                                    : round($this->selectedSubmission->nominal); // Default ambil dari nominal pengajuan
        $this->file_lpj_lama = $this->selectedSubmission->file_lpj;
        
        $this->isModalOpen = true;
    }

    // ==========================================
    // FUNGSI: SIMPAN LPJ
    // ==========================================
    public function saveLpj()
    {
        $rules = [
            'nominal_realisasi' => 'required|numeric|min:0',
        ];

        // Jika file LPJ lama belum ada, maka file upload bersifat wajib
        if (!$this->file_lpj_lama) {
            $rules['file_lpj'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'; // Maks 5MB untuk scan banyak struk
        } else {
            $rules['file_lpj'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }

        $this->validate($rules, [
            'file_lpj.required' => 'Dokumen bukti pembayaran (struk/nota) wajib dilampirkan.',
        ]);

        if ($this->selectedSubmission) {
            $data = [
                'nominal_realisasi' => $this->nominal_realisasi,
                'status_lpj' => 'menunggu_verifikasi', // Otomatis masuk antrean verifikasi keuangan
            ];

            // Handle File Upload
            if ($this->file_lpj) {
                if ($this->file_lpj_lama) {
                    Storage::disk('public')->delete($this->file_lpj_lama);
                }
                $data['file_lpj'] = $this->file_lpj->store('lpj_files', 'public');
            }

            $this->selectedSubmission->update($data);
            session()->flash('success', 'Laporan LPJ berhasil dikirim untuk diverifikasi!');
        }

        $this->isModalOpen = false;
        $this->selectedSubmission = null;
    }

    // ==========================================
    // FUNGSI: READ DATA
    // ==========================================
    public function with(): array
    {
        // Hanya tampilkan pengajuan milik user yang sudah APPROVED (uang cair)
        $query = FundSubmission::with('unit')
            ->where('user_id', Auth::id())
            ->where('status', 'approved');

        if ($this->filterStatusLpj) {
            $query->where('status_lpj', $this->filterStatusLpj);
        }

        if ($this->search) {
            $query->where('keperluan', 'like', '%' . $this->search . '%');
        }

        return [
            // Urutkan berdasarkan yang belum dikerjakan LPJ-nya agar muncul di atas
            'submissions' => $query->orderByRaw("FIELD(status_lpj, 'belum', 'menunggu_verifikasi', 'selesai')")
                                   ->latest()
                                   ->paginate(10),
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Laporan Pertanggungjawaban (LPJ)</h1>
            <p class="text-sm text-theme-muted mt-1">Unggah bukti transaksi dan nominal realisasi dari dana yang telah Anda terima.</p>
        </div>
    </div>

    <!-- Alert Sukses/Error -->
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Kotak Filter & Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex flex-col md:flex-row items-center gap-3">
        <!-- Filter Status LPJ -->
        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterStatusLpj" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium">
                <option value="">Semua Status LPJ</option>
                <option value="belum">Belum Dilaporkan</option>
                <option value="menunggu_verifikasi">Menunggu Verifikasi Keuangan</option>
                <option value="selesai">LPJ Selesai (Clear)</option>
            </select>
        </div>

        <!-- Pencarian -->
        <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari rincian keperluan..." class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all">
        </div>
    </div>

    <!-- Tabel Data LPJ -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-40">Info Pencairan</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Detail Kegiatan</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Dana Awal vs Realisasi</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center w-36">Status LPJ</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($submissions as $item)
                        <tr class="hover:bg-theme-body/30 transition-colors group" wire:key="lpj-{{ $item->id }}">
                            
                            <!-- Tanggal Pencairan -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm font-bold text-theme-text">{{ $item->updated_at->translatedFormat('d M Y') }}</div>
                                <div class="text-[10px] text-theme-muted mt-0.5">DCAIRKAN</div>
                            </td>
                            
                            <!-- Keperluan -->
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    @if($item->tipe_pengajuan === 'lembaga')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-900/30 dark:border-blue-800/50 dark:text-blue-400">
                                            Lembaga: {{ $item->unit ? ($item->unit->kode_unit ?? $item->unit->nama_unit) : '-' }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-900/30 dark:border-emerald-800/50 dark:text-emerald-400">
                                            Pribadi
                                        </span>
                                    @endif
                                </div>
                                <p class="text-sm font-medium text-theme-text">{{ $item->keperluan }}</p>
                            </td>

                            <!-- Nominal Awal & Realisasi -->
                            <td class="px-6 py-4 align-top text-right">
                                <div class="text-[10px] text-theme-muted uppercase tracking-wider mb-0.5">Dana Cair</div>
                                <div class="text-sm font-bold text-theme-text mb-2">Rp {{ number_format($item->nominal, 0, ',', '.') }}</div>
                                
                                <div class="text-[10px] text-theme-muted uppercase tracking-wider mb-0.5">Terpakai (Realisasi)</div>
                                @if($item->nominal_realisasi)
                                    <div class="text-sm font-extrabold text-blue-600 dark:text-blue-400">Rp {{ number_format($item->nominal_realisasi, 0, ',', '.') }}</div>
                                @else
                                    <div class="text-sm italic text-theme-muted">-</div>
                                @endif
                            </td>

                            <!-- Status LPJ -->
                            <td class="px-6 py-4 align-top text-center">
                                @if($item->status_lpj === 'belum')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">Belum Lapor</span>
                                @elseif($item->status_lpj === 'menunggu_verifikasi')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20">Proses Cek</span>
                                @elseif($item->status_lpj === 'selesai')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">Selesai / Clear</span>
                                @endif
                                
                                @if($item->file_lpj)
                                    <a href="{{ Storage::url($item->file_lpj) }}" target="_blank" class="mt-2 text-[10px] flex items-center justify-center w-full gap-1 text-theme-muted hover:text-primary transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                        Lihat Bukti
                                    </a>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-6 py-4 align-top text-right">
                                @if($item->status_lpj !== 'selesai')
                                    <button wire:click="openModal({{ $item->id }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-lg transition-colors shadow-sm shadow-primary/20">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                        Upload LPJ
                                    </button>
                                @else
                                    <div class="flex justify-end pr-2">
                                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Selesai dan Terkunci"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-theme-body mb-4 border border-theme-border shadow-inner">
                                    <svg class="w-10 h-10 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <h4 class="text-base font-bold text-theme-text uppercase tracking-tight">Tidak Ada Data LPJ</h4>
                                <p class="text-sm text-theme-muted mt-1">Anda belum memiliki dana yang cair untuk dilaporkan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginasi -->
        @if($submissions->hasPages())
            <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- MODAL FORM LPJ -->
    <!-- ========================================== -->
    @if($isModalOpen && $selectedSubmission)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                
                <!-- Header Sticky -->
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center z-10 shrink-0">
                    <h3 class="text-lg font-bold text-theme-text">Form Pertanggungjawaban</h3>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="saveLpj" class="flex flex-col overflow-hidden flex-1">
                    <!-- Body Scrollable -->
                    <div class="p-6 overflow-y-auto custom-scrollbar space-y-6">
                        
                        <!-- Ringkasan Info Dana -->
                        <div class="bg-blue-50 dark:bg-blue-900/10 p-4 rounded-xl border border-blue-200 dark:border-blue-800/50">
                            <p class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider mb-1">Total Dana Disetujui/Cair</p>
                            <p class="text-xl font-extrabold text-theme-text">Rp {{ number_format($selectedSubmission->nominal, 0, ',', '.') }}</p>
                            <p class="text-xs text-theme-muted mt-2 leading-relaxed">
                                <strong>Keperluan:</strong> {{ $selectedSubmission->keperluan }}
                            </p>
                        </div>

                        <!-- Nominal Realisasi -->
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Nominal Realisasi Terpakai (Rp) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted font-bold">Rp</span>
                                <input type="number" wire:model="nominal_realisasi" min="0" class="block w-full pl-9 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text font-bold">
                            </div>
                            <p class="text-[10px] text-theme-muted mt-1.5">Masukkan jumlah riil uang yang dibelanjakan sesuai nota.</p>
                            @error('nominal_realisasi') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Upload Bukti -->
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Upload Bukti Struk/Kuitansi <span class="text-red-500">*</span></label>
                            <div class="border-2 border-dashed border-theme-border rounded-xl p-4 text-center hover:bg-theme-body transition-colors relative">
                                <input type="file" wire:model="file_lpj" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" title="Klik untuk mengunggah file">
                                
                                <div class="pointer-events-none">
                                    <svg class="mx-auto h-8 w-8 text-theme-muted mb-2" stroke="currentColor" fill="none" viewBox="0 0 48 48"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    <p class="text-sm font-bold text-primary">Klik untuk memilih file PDF/Gambar</p>
                                    <p class="text-[10px] text-theme-muted mt-1">Maksimal 5MB. Gabungkan nota jika lebih dari satu.</p>
                                </div>
                            </div>
                            
                            <div wire:loading wire:target="file_lpj" class="text-[10px] font-bold text-primary mt-2 flex items-center gap-1">
                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Mengunggah file...
                            </div>

                            @if($file_lpj)
                                <div class="text-[10px] font-bold text-emerald-600 mt-2 flex items-center gap-1 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-1.5 rounded w-full">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> 
                                    File siap disubmit: {{ $file_lpj->getClientOriginalName() }}
                                </div>
                            @elseif($file_lpj_lama)
                                <div class="text-[10px] font-bold text-blue-600 mt-2 flex items-center gap-1 bg-blue-50 dark:bg-blue-500/10 px-2 py-1.5 rounded w-full">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 
                                    Sudah ada file LPJ terlampir sebelumnya.
                                </div>
                            @endif

                            @error('file_lpj') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                        </div>

                    </div>
                    
                    <!-- Footer Sticky -->
                    <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">
                            Batal
                        </button>
                        
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all flex items-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveLpj">Simpan & Ajukan Verifikasi</span>
                            <span wire:loading wire:target="saveLpj">Memproses...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>