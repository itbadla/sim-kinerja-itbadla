<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Logbook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $filterBulan = '';

    // ==========================================
    // STATE: MODAL FORM (CREATE & EDIT)
    // ==========================================
    public $isModalOpen = false;
    public $logbookId = null;
    public $unit_id = null;
    public $userUnits = [];
    public $tanggal;
    public $jam_mulai;
    public $jam_selesai;
    public $kategori = 'tugas_utama';
    public $deskripsi_aktivitas = '';
    public $output = '';
    public $link_bukti = '';
    public $file_bukti; // Untuk upload file baru
    public $file_bukti_lama; // Untuk menampilkan nama file yang sudah ada

    // ==========================================
    // STATE: MODAL HAPUS (DELETE)
    // ==========================================
    public $isDeleteModalOpen = false;
    public ?int $logbookToDeleteId = null;

    public function mount()
    {
        $this->filterBulan = date('Y-m'); // Default filter: bulan ini
        $this->tanggal = date('Y-m-d'); // Default input form: hari ini
        
        // Load units for user
        $this->userUnits = Auth::user()->units()->get();
        if ($this->userUnits->count() > 0) {
            $this->unit_id = $this->userUnits->first()->id;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterBulan()
    {
        $this->resetPage();
    }

    // ==========================================
    // FUNGSI: BUKA MODAL FORM
    // ==========================================
    public function openModal($id = null)
    {
        $this->resetValidation();
        $this->reset(['file_bukti', 'file_bukti_lama']); // Reset file setiap buka modal
        
        if ($id) {
            $logbook = Logbook::where('user_id', Auth::id())->findOrFail($id);
            
            // Cegah edit jika sudah disetujui
            if ($logbook->status === 'approved') {
                session()->flash('error', 'Logbook yang sudah disetujui tidak dapat diubah.');
                return;
            }

            $this->logbookId = $id;
            $this->unit_id = $logbook->unit_id;
            $this->tanggal = $logbook->tanggal->format('Y-m-d');
            $this->jam_mulai = $logbook->jam_mulai->format('H:i');
            $this->jam_selesai = $logbook->jam_selesai->format('H:i');
            $this->kategori = $logbook->kategori;
            $this->deskripsi_aktivitas = $logbook->deskripsi_aktivitas;
            $this->output = $logbook->output;
            $this->link_bukti = $logbook->link_bukti;
            $this->file_bukti_lama = $logbook->file_bukti;
        } else {
            $this->reset(['logbookId', 'deskripsi_aktivitas', 'output', 'link_bukti']);
            if ($this->userUnits->count() > 0) {
                $this->unit_id = $this->userUnits->first()->id;
            } else {
                $this->unit_id = null;
            }
            $this->tanggal = date('Y-m-d');
            $this->kategori = 'tugas_utama';
            $this->jam_mulai = '08:00';
            $this->jam_selesai = '16:00';
        }
        
        $this->isModalOpen = true;
    }

    // ==========================================
    // FUNGSI: SIMPAN DATA
    // ==========================================
    public function saveLogbook($status = 'pending')
    {
        $this->validate([
            'unit_id' => 'required|exists:units,id',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required|after:jam_mulai',
            'kategori' => 'required|string',
            'deskripsi_aktivitas' => 'required|string|min:10',
            'output' => 'nullable|string|max:255',
            'link_bukti' => 'nullable|url',
            'file_bukti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Maks 2MB
        ]);

        $data = [
            'user_id' => Auth::id(),
            'unit_id' => $this->unit_id,
            'tanggal' => $this->tanggal,
            'jam_mulai' => $this->jam_mulai,
            'jam_selesai' => $this->jam_selesai,
            'kategori' => $this->kategori,
            'deskripsi_aktivitas' => $this->deskripsi_aktivitas,
            'output' => $this->output,
            'link_bukti' => $this->link_bukti,
            'status' => $status, // Bisa 'draft' atau 'pending' (siap diverifikasi)
        ];

        // Handle File Upload
        if ($this->file_bukti) {
            // Hapus file lama jika ada
            if ($this->logbookId && $this->file_bukti_lama) {
                Storage::disk('public')->delete($this->file_bukti_lama);
            }
            $data['file_bukti'] = $this->file_bukti->store('logbook_files', 'public');
        }

        Logbook::updateOrCreate(['id' => $this->logbookId], $data);

        $this->isModalOpen = false;
    }

    // ==========================================
    // FUNGSI: HAPUS DATA
    // ==========================================
    public function confirmDelete($id)
    {
        $logbook = Logbook::where('user_id', Auth::id())->findOrFail($id);
        
        if ($logbook->status === 'approved') {
            session()->flash('error', 'Logbook yang sudah disetujui tidak dapat dihapus.');
            return;
        }

        $this->logbookToDeleteId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function deleteLogbook()
    {
        if ($this->logbookToDeleteId) {
            $logbook = Logbook::findOrFail($this->logbookToDeleteId);
            
            // Hapus file fisiknya juga jika ada
            if ($logbook->file_bukti) {
                Storage::disk('public')->delete($logbook->file_bukti);
            }
            
            $logbook->delete();
        }
        
        $this->isDeleteModalOpen = false;
        $this->logbookToDeleteId = null;
    }

    // ==========================================
    // FUNGSI: READ DATA
    // ==========================================
    public function with(): array
    {
        $query = Logbook::where('user_id', Auth::id())
            ->whereYear('tanggal', substr($this->filterBulan, 0, 4))
            ->whereMonth('tanggal', substr($this->filterBulan, 5, 2));

        if ($this->search) {
            $query->where(function($q) {
                $q->where('deskripsi_aktivitas', 'like', '%' . $this->search . '%')
                  ->orWhere('kategori', 'like', '%' . $this->search . '%');
            });
        }

        return [
            'logbooks' => $query->orderBy('tanggal', 'desc')->orderBy('jam_mulai', 'desc')->paginate(10),
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Logbook Harian</h1>
            <p class="text-sm text-theme-muted mt-1">Catat aktivitas harian dan laporan kinerja Anda di sini.</p>
        </div>
        
        <button wire:click="openModal" class="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tulis Logbook
        </button>
    </div>

    <!-- Alert Error (Jika mencoba edit yg sudah di-approve) -->
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- Kotak Filter & Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex flex-col md:flex-row items-center gap-3">
        <!-- Filter Bulan -->
        <div class="w-full md:w-auto flex-shrink-0">
            <input type="month" wire:model.live="filterBulan" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium transition-all">
        </div>

        <!-- Pencarian -->
        <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                autocomplete="off" 
                class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all" 
                placeholder="Cari aktivitas atau keterangan..."
            >
        </div>
    </div>

    <!-- Tabel Data (READ) -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-40">Waktu</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Aktivitas & Hasil</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center w-32">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($logbooks as $log)
                        <tr class="hover:bg-theme-body/30 transition-colors group" wire:key="logbook-{{ $log->id }}">
                            <!-- Kolom Waktu -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm font-bold text-theme-text">{{ $log->tanggal->translatedFormat('d M Y') }}</div>
                                <div class="text-xs text-theme-muted mt-1 font-mono bg-theme-body inline-block px-2 py-0.5 rounded border border-theme-border">
                                    {{ $log->jam_mulai->format('H:i') }} - {{ $log->jam_selesai->format('H:i') }}
                                </div>
                            </td>
                            
                            <!-- Kolom Aktivitas -->
                            <td class="px-6 py-4 align-top">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-primary bg-primary/10 px-2 py-0.5 rounded border border-primary/20">
                                        {{ str_replace('_', ' ', $log->kategori) }}
                                    </span>
                                </div>
                                <p class="text-sm text-theme-text leading-relaxed font-medium">
                                    {{ $log->deskripsi_aktivitas }}
                                </p>
                                @if($log->output)
                                    <p class="text-xs text-theme-muted mt-2 border-l-2 border-theme-border pl-2">
                                        <span class="font-semibold text-theme-text">Output:</span> {{ $log->output }}
                                    </p>
                                @endif
                                
                                <!-- Lampiran -->
                                @if($log->file_bukti || $log->link_bukti)
                                    <div class="flex gap-2 mt-3">
                                        @if($log->file_bukti)
                                            <a href="{{ Storage::url($log->file_bukti) }}" target="_blank" class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-blue-600 hover:text-blue-700 bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 px-2.5 py-1 rounded-md transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                                Lihat File
                                            </a>
                                        @endif
                                        @if($log->link_bukti)
                                            <a href="{{ $log->link_bukti }}" target="_blank" class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-purple-600 hover:text-purple-700 bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/20 px-2.5 py-1 rounded-md transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                                Buka Link
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- Kolom Status -->
                            <td class="px-6 py-4 align-top text-center">
                                @if($log->status === 'draft')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700">Draft</span>
                                @elseif($log->status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20">Proses</span>
                                @elseif($log->status === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">Disetujui</span>
                                @elseif($log->status === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">Ditolak</span>
                                @endif
                                
                                @if($log->catatan_verifikator)
                                    <button class="mt-2 text-[10px] flex items-center justify-center w-full gap-1 text-theme-muted hover:text-primary transition-colors" title="{{ $log->catatan_verifikator }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                        Ada Catatan
                                    </button>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-6 py-4 align-top text-right">
                                @if($log->status !== 'approved')
                                    <div class="flex items-center justify-end gap-1 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button wire:click="openModal({{ $log->id }})" class="text-theme-muted hover:text-primary transition-colors p-2 rounded-lg hover:bg-theme-body border border-transparent hover:border-theme-border" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ $log->id }})" class="text-theme-muted hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                @else
                                    <div class="flex justify-end pr-2">
                                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Terkunci. Sudah disetujui."><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-theme-body mb-4 border border-theme-border shadow-inner">
                                    <svg class="w-10 h-10 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                </div>
                                <h4 class="text-base font-bold text-theme-text uppercase tracking-tight">Belum Ada Aktivitas</h4>
                                <p class="text-sm text-theme-muted mt-1">Klik tombol 'Tulis Logbook' untuk menambahkan rekam jejak Anda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginasi -->
        @if($logbooks->hasPages())
            <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">
                {{ $logbooks->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- MODAL FORM (TULIS / EDIT LOGBOOK) -->
    <!-- ========================================== -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                <!-- Header Sticky -->
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center z-10 shrink-0">
                    <h3 class="text-lg font-bold text-theme-text">{{ $logbookId ? 'Edit Logbook' : 'Tulis Logbook Baru' }}</h3>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- Wrapper Form yang flex-col dan overflow-hidden -->
                <form wire:submit.prevent="saveLogbook('pending')" class="flex flex-col overflow-hidden">
                    <!-- Body Scrollable -->
                    <div class="p-6 overflow-y-auto custom-scrollbar space-y-6 flex-1">
                        
                        <!-- Pilihan Unit (Jika > 1) -->
                        @if(count($userUnits) > 1)
                            <div>
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Kirim Logbook Ke Unit <span class="text-red-500">*</span></label>
                                <select wire:model="unit_id" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                    @foreach($userUnits as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                                    @endforeach
                                </select>
                                @error('unit_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <!-- Waktu Pelaksanaan -->
                        <div class="bg-theme-body p-4 rounded-xl border border-theme-border">
                            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-3">Waktu Pelaksanaan</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs text-theme-muted mb-1.5">Tanggal <span class="text-red-500">*</span></label>
                                    <input type="date" wire:model="tanggal" class="block w-full border border-theme-border bg-theme-surface rounded-lg py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                    @error('tanggal') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-theme-muted mb-1.5">Jam Mulai <span class="text-red-500">*</span></label>
                                    <input type="time" wire:model="jam_mulai" class="block w-full border border-theme-border bg-theme-surface rounded-lg py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-mono">
                                    @error('jam_mulai') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-theme-muted mb-1.5">Jam Selesai <span class="text-red-500">*</span></label>
                                    <input type="time" wire:model="jam_selesai" class="block w-full border border-theme-border bg-theme-surface rounded-lg py-2 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-mono">
                                    @error('jam_selesai') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Kategori Aktivitas -->
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Kategori <span class="text-red-500">*</span></label>
                            <select wire:model="kategori" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text">
                                <option value="tugas_utama">Tugas Utama (Tupoksi)</option>
                                <option value="tugas_tambahan">Tugas Tambahan / Kepanitiaan</option>
                                <option value="dosen_tridharma">Tri Dharma (Khusus Dosen)</option>
                                <option value="magang">Aktivitas Magang</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                            @error('kategori') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Deskripsi -->
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Deskripsi Aktivitas <span class="text-red-500">*</span></label>
                            <textarea wire:model="deskripsi_aktivitas" rows="4" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="Jelaskan secara rinci apa yang Anda kerjakan..."></textarea>
                            @error('deskripsi_aktivitas') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Output -->
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Output / Hasil Akhir <span class="text-theme-muted font-normal normal-case">(Opsional)</span></label>
                            <input type="text" wire:model="output" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="Contoh: 1 Dokumen PDF, 5 Modul Aplikasi diselesaikan">
                            @error('output') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Bukti Dukung -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-theme-border pt-4">
                            <div>
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Tautan (Link) Bukti <span class="text-theme-muted font-normal normal-case">(Opsional)</span></label>
                                <input type="url" wire:model="link_bukti" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="https://drive.google.com/.../view">
                                @error('link_bukti') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Unggah File Fisik <span class="text-theme-muted font-normal normal-case">(Opsional, Maks 2MB)</span></label>
                                <input type="file" wire:model="file_bukti" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm text-theme-text file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-primary-hover file:cursor-pointer file:transition-colors">
                                <div wire:loading wire:target="file_bukti" class="text-[10px] font-bold text-primary mt-1 flex items-center gap-1">
                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses file...
                                </div>
                                @if($file_bukti_lama && !$file_bukti)
                                    <div class="text-[10px] font-bold text-emerald-600 mt-1.5 flex items-center gap-1 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-1 rounded w-max">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 
                                        Ada file tersimpan
                                    </div>
                                @endif
                                @error('file_bukti') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer Sticky -->
                    <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">
                            Batal
                        </button>
                        
                        <!-- Simpan sebagai Draft -->
                        <button type="button" wire:click="saveLogbook('draft')" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-bold text-theme-text bg-theme-body border border-theme-border hover:bg-theme-surface rounded-xl shadow-sm transition-all disabled:opacity-50">
                            Simpan Draft
                        </button>
                        
                        <!-- Simpan & Ajukan -->
                        <button type="submit" wire:loading.attr="disabled" class="px-5 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all flex items-center gap-2 disabled:opacity-50">
                            <svg wire:loading.remove wire:target="saveLogbook" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <svg wire:loading wire:target="saveLogbook" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span wire:loading.remove wire:target="saveLogbook">Kirim ke Atasan</span>
                            <span wire:loading wire:target="saveLogbook">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL DELETE (KONFIRMASI HAPUS) -->
    <!-- ========================================== -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-sm overflow-hidden text-center p-6" onclick="event.stopPropagation()">
                <div class="w-16 h-16 bg-red-100 dark:bg-red-500/20 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-theme-text mb-2">Hapus Logbook?</h3>
                <p class="text-sm text-theme-muted mb-6">Aktivitas yang dihapus tidak dapat dikembalikan. Yakin ingin menghapus?</p>
                <div class="flex justify-center gap-3">
                    <button type="button" wire:click="$set('isDeleteModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-theme-muted hover:text-theme-text bg-theme-body rounded-xl border border-theme-border transition-colors w-full">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteLogbook" wire:loading.attr="disabled" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-md transition-all w-full flex items-center justify-center gap-2 disabled:opacity-50">
                        <span wire:loading.remove wire:target="deleteLogbook">Ya, Hapus</span>
                        <span wire:loading wire:target="deleteLogbook">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>