<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\FundSubmission;
use App\Models\FundDisbursement;
use App\Models\Unit;
use App\Models\Periode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $filterStatus = 'pending'; 
    public $filterTipe = '';
    public $selectedPeriodeId = ''; 
    
    // Hak Akses Tab 2
    public $isKeuangan = false;
    
    // 3 Tab Meja Kerja Keuangan
    public $activeTab = 'verifikasi_proposal'; 

    // ==========================================
    // STATE: MODAL 1 (VERIFIKASI PROPOSAL)
    // ==========================================
    public $isModalOpen = false;
    public ?FundSubmission $selectedSubmission = null;
    public $actionStatus = ''; 
    public $catatan = '';
    public $nominal_disetujui = 0; 
    public $skema_pencairan = 'lumpsum';
    public $jumlah_termin = 2; 
    public $termin_nominals = []; 
    public $total_input_termin = 0; 

    // ==========================================
    // STATE: MODAL 2 (PROSES PENCAIRAN / TRANSFER)
    // ==========================================
    public $isPencairanModalOpen = false;
    public ?FundDisbursement $selectedDisbursement = null;
    public $tanggal_cair;
    public $bukti_transfer_kampus;
    public $bukti_transfer_kampus_lama;

    // ==========================================
    // STATE: MODAL 3 (VERIFIKASI LPJ & KEMBALIAN)
    // ==========================================
    public $isLpjModalOpen = false;
    public ?FundDisbursement $selectedLpj = null;
    public $catatanLpj = '';

    public function mount()
    {
        $currentPeriode = Periode::where('is_current', true)->first();
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }
        $this->tanggal_cair = date('Y-m-d');
        $this->isKeuangan = Auth::user()->hasRole(['Super Admin', 'admin', 'keuangan']);
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->search = '';
        
        if ($tab === 'verifikasi_proposal') {
            $this->filterStatus = 'pending';
        } elseif ($tab === 'verifikasi_lpj') {
            $this->filterStatus = 'menunggu_verifikasi';
        } else {
            $this->filterStatus = '';
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    public function updatingFilterTipe() { $this->resetPage(); }
    public function updatingSelectedPeriodeId() { $this->resetPage(); }

    // ----------------------------------------------------------------------
    // FUNGSI TAB 1: VERIFIKASI PROPOSAL
    // ----------------------------------------------------------------------
    public function openModal($id)
    {
        $this->resetValidation();
        $this->selectedSubmission = FundSubmission::with(['user', 'unit'])->findOrFail($id);
        
        if ($this->selectedSubmission->status_pengajuan === 'approved') {
            $isProcessed = FundDisbursement::where('fund_submission_id', $this->selectedSubmission->id)
                ->where(function($q) {
                    $q->where('status_cair', '!=', 'pending')
                      ->orWhere('status_lpj', '!=', 'belum');
                })->exists();
                
            if ($isProcessed) {
                session()->flash('error', 'Tidak dapat memodifikasi! Sebagian dana sudah dicairkan atau sedang dalam proses pelaporan LPJ.');
                return;
            }
        }

        $this->actionStatus = $this->selectedSubmission->status_pengajuan !== 'pending' ? $this->selectedSubmission->status_pengajuan : '';
        $this->catatan = $this->selectedSubmission->catatan_verifikator ?? '';
        $this->nominal_disetujui = floatval($this->selectedSubmission->nominal_disetujui ?? $this->selectedSubmission->nominal_total);
        $this->skema_pencairan = $this->selectedSubmission->skema_pencairan ?? 'lumpsum';
        
        $existingDisbursements = FundDisbursement::where('fund_submission_id', $this->selectedSubmission->id)->orderBy('termin_ke')->get();
        
        if ($this->selectedSubmission->status_pengajuan === 'approved' && $existingDisbursements->count() > 0) {
            $this->jumlah_termin = $existingDisbursements->count() < 2 ? 2 : $existingDisbursements->count();
            $this->termin_nominals = [];
            foreach ($existingDisbursements as $disb) {
                $this->termin_nominals[$disb->termin_ke] = floatval($disb->nominal_cair);
            }
        } else {
            $usulanTermin = 2;
            if ($this->skema_pencairan === 'termin') {
                if (preg_match('/\[Usulan (\d+) Termin Pencairan\]/', $this->selectedSubmission->keperluan, $matches)) {
                    $usulanTermin = (int) $matches[1];
                }
            }
            $this->jumlah_termin = $usulanTermin;
            $this->generateTerminNominals();
        }
        
        $this->updatedTerminNominals();
        $this->isModalOpen = true;
    }

    public function updatedNominalDisetujui() { $this->generateTerminNominals(); }
    public function updatedSkemaPencairan() { $this->generateTerminNominals(); }
    
    public function updatedJumlahTermin() 
    { 
        $jml = (int) $this->jumlah_termin;
        if ($jml > 24) $this->jumlah_termin = 24;
        elseif ($jml < 2 && $this->jumlah_termin !== '') $this->jumlah_termin = 2;
        $this->generateTerminNominals(); 
    }
    
    public function updatedTerminNominals() { 
        $this->total_input_termin = array_sum(array_map('floatval', $this->termin_nominals)); 
    }

    private function generateTerminNominals()
    {
        if (!$this->selectedSubmission) return;
        $total = floatval($this->nominal_disetujui);
        $this->termin_nominals = [];

        if ($this->skema_pencairan === 'lumpsum') {
            $this->termin_nominals[1] = $total;
        } else {
            $jml = (int) $this->jumlah_termin;
            if ($jml < 2) return; 

            $base = floor($total / $jml);
            $remainder = $total - ($base * $jml);
            
            for ($i = 1; $i <= $jml; $i++) {
                $this->termin_nominals[$i] = $base + ($i === 1 ? $remainder : 0); 
            }
        }
        $this->updatedTerminNominals();
    }

    public function saveVerification()
    {
        $periode = Periode::find($this->selectedPeriodeId);
        if (!$periode || $periode->status === 'closed') {
            session()->flash('error', 'Gagal memverifikasi! Periode ini sudah dikunci.');
            return;
        }

        // VALIDASI DINAMIS: Tergantung pada aksi Setuju atau Tolak
        $rules = [
            'actionStatus' => 'required|in:approved,rejected',
        ];

        if ($this->actionStatus === 'approved') {
            $rules['nominal_disetujui'] = 'required|numeric|min:0';
            if ($this->skema_pencairan === 'termin') {
                $rules['jumlah_termin'] = 'required|integer|min:2|max:24';
            }
        } elseif ($this->actionStatus === 'rejected') {
            $rules['catatan'] = 'required|string|min:5|max:500';
        }

        $this->validate($rules, [
            'actionStatus.required' => 'Anda belum memberikan Keputusan Akhir.',
            'catatan.required' => 'Catatan revisi/penolakan wajib diisi.',
            'catatan.min' => 'Catatan terlalu singkat.',
        ]);

        if ($this->actionStatus === 'approved') {
            $total_input = array_sum(array_map('floatval', $this->termin_nominals));
            $target_acc = floatval($this->nominal_disetujui);
            if (round($total_input) !== round($target_acc)) {
                session()->flash('error', 'Total alokasi termin tidak sama dengan Nominal Akhir Disetujui.');
                return;
            }
        }

        DB::beginTransaction();
        try {
            $this->selectedSubmission->update([
                'status_pengajuan' => $this->actionStatus,
                'catatan_verifikator' => $this->catatan,
                'nominal_disetujui' => $this->actionStatus === 'approved' ? $this->nominal_disetujui : null,
                'skema_pencairan' => $this->actionStatus === 'approved' ? $this->skema_pencairan : 'lumpsum',
                'verified_by' => auth()->id(), 
                'verified_at' => now(),
            ]);

            FundDisbursement::where('fund_submission_id', $this->selectedSubmission->id)->delete();

            if ($this->actionStatus === 'approved') {
                foreach ($this->termin_nominals as $index => $nominal_cair) {
                    FundDisbursement::create([
                        'fund_submission_id' => $this->selectedSubmission->id,
                        'termin_ke' => $index,
                        'nominal_cair' => $nominal_cair,
                        'status_cair' => 'pending', 
                        'status_lpj' => 'belum',
                        'status_pengembalian' => 'tidak_ada',
                    ]);
                }
            }

            DB::commit();
            session()->flash('success', 'Keputusan verifikasi berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }

        $this->isModalOpen = false;
        $this->selectedSubmission = null;
    }

    // ----------------------------------------------------------------------
    // FUNGSI TAB 2: PROSES PENCAIRAN (TRANSFER)
    // ----------------------------------------------------------------------
    public function openPencairanModal($id)
    {
        $this->resetValidation();
        $this->reset(['bukti_transfer_kampus']);
        $this->selectedDisbursement = FundDisbursement::with('submission.user')->findOrFail($id);
        $this->tanggal_cair = $this->selectedDisbursement->tanggal_cair ? $this->selectedDisbursement->tanggal_cair->format('Y-m-d') : date('Y-m-d');
        $this->bukti_transfer_kampus_lama = $this->selectedDisbursement->bukti_transfer_kampus;
        $this->isPencairanModalOpen = true;
    }

    public function prosesPencairan()
    {
        $rules = ['tanggal_cair' => 'required|date'];
        
        if (!$this->bukti_transfer_kampus_lama) {
            $rules['bukti_transfer_kampus'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120';
        } else {
            $rules['bukti_transfer_kampus'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }

        $this->validate($rules, [
            'bukti_transfer_kampus.required' => 'Wajib melampirkan bukti struk transfer/pencairan dana.',
        ]);

        $data = [
            'status_cair' => 'cair',
            'tanggal_cair' => $this->tanggal_cair,
            'cair_processed_by' => auth()->id(), 
        ];

        if ($this->bukti_transfer_kampus) {
            if ($this->bukti_transfer_kampus_lama) {
                Storage::disk('public')->delete($this->bukti_transfer_kampus_lama);
            }
            $data['bukti_transfer_kampus'] = $this->bukti_transfer_kampus->store('bukti_transfer', 'public');
        }

        $this->selectedDisbursement->update($data);

        session()->flash('success', 'Dana termin ke-'.$this->selectedDisbursement->termin_ke.' berhasil ditandai cair.');
        $this->isPencairanModalOpen = false;
        $this->selectedDisbursement = null;
    }

    // FUNGSI: Tarik Status Cair ke Pending
    public function cancelPencairan($id)
    {
        $disbursement = FundDisbursement::findOrFail($id);
        if ($disbursement->status_lpj !== 'belum') {
            session()->flash('error', 'Tidak dapat membatalkan karena LPJ sudah mulai dilaporkan oleh Dosen.');
            return;
        }
        $disbursement->update([
            'status_cair' => 'pending',
            'tanggal_cair' => null,
            'cair_processed_by' => null
        ]);
        session()->flash('success', 'Status pencairan berhasil dibatalkan dan ditarik kembali ke antrean.');
    }

    // ----------------------------------------------------------------------
    // FUNGSI TAB 3: VERIFIKASI LPJ & SISA DANA (SiLPA)
    // ----------------------------------------------------------------------
    public function openLpjModal($id)
    {
        $this->resetValidation();
        $this->selectedLpj = FundDisbursement::with(['submission.user', 'submission.unit'])->findOrFail($id);
        $this->catatanLpj = $this->selectedLpj->catatan_revisi_lpj ?? '';
        $this->isLpjModalOpen = true;
    }

    public function approveLpj()
    {
        if ($this->selectedLpj) {
            $this->selectedLpj->update([
                'status_lpj' => 'selesai',
                'status_pengembalian' => $this->selectedLpj->nominal_kembali > 0 ? 'lunas' : 'tidak_ada',
                'catatan_revisi_lpj' => null,
                'lpj_verified_by' => auth()->id(), 
            ]);
            session()->flash('success', 'LPJ Termin ke-'.$this->selectedLpj->termin_ke.' berhasil disetujui. Termin selanjutnya otomatis terbuka (jika ada).');
            $this->isLpjModalOpen = false;
        }
    }

    public function rejectLpj()
    {
        $this->validate(['catatanLpj' => 'required|string|min:5'], ['catatanLpj.required' => 'Wajib memberikan alasan revisi untuk Dosen.']);
        
        if ($this->selectedLpj) {
            $this->selectedLpj->update([
                'status_lpj' => 'belum',
                'status_pengembalian' => $this->selectedLpj->nominal_kembali > 0 ? 'menunggu_verifikasi' : 'tidak_ada',
                'catatan_revisi_lpj' => $this->catatanLpj,
                'lpj_verified_by' => auth()->id(), 
            ]);
            session()->flash('error', 'LPJ dikembalikan ke pengaju untuk direvisi.');
            $this->isLpjModalOpen = false;
        }
    }

    // FUNGSI: Tarik Status Selesai LPJ ke Pending
    public function cancelLpjVerification($id)
    {
        $lpj = FundDisbursement::findOrFail($id);
        $lpj->update([
            'status_lpj' => 'menunggu_verifikasi',
            'status_pengembalian' => $lpj->nominal_kembali > 0 ? 'menunggu_verifikasi' : 'tidak_ada',
            'lpj_verified_by' => null,
        ]);
        session()->flash('success', 'Status Selesai pada LPJ dibatalkan, dokumen kembali ke antrean periksa.');
    }

    // ==========================================
    // FUNGSI: READ DATA (Menarik Data Untuk 3 Meja Kerja)
    // ==========================================
    public function with(): array
    {
        $user = Auth::user();
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);

        $submissions = collect();
        $pencairanSubmissions = collect(); // Grouping untuk Tab 2
        $lpjVerifications = collect();
        
        if ($selectedPeriode) {
            $headedUnitIds = Unit::where('kepala_unit_id', $user->id)->pluck('id');

            // ----------------------------------------------------
            // DATA TAB 1: PERSETUJUAN PROPOSAL AWAL
            // ----------------------------------------------------
            if ($this->activeTab === 'verifikasi_proposal') {
                $query = FundSubmission::with(['user', 'unit', 'disbursements', 'verifikator'])
                    ->where('periode_id', $selectedPeriode->id)
                    ->latest();

                if (!$this->isKeuangan) {
                    $query->whereIn('unit_id', $headedUnitIds)->where('user_id', '!=', $user->id);
                }

                if ($this->filterStatus) {
                    $query->where('status_pengajuan', $this->filterStatus);
                }

                if ($this->filterTipe) {
                    $query->where('tipe_pengajuan', $this->filterTipe);
                }

                if ($this->search) {
                    $query->where(function($q) {
                        $q->where('keperluan', 'like', '%' . $this->search . '%')
                          ->orWhereHas('user', function($qu) { $qu->where('name', 'like', '%' . $this->search . '%'); });
                    });
                }
                $submissions = $query->paginate(10);
            
            // ----------------------------------------------------
            // DATA TAB 2: ANTREAN PENCAIRAN (KASIR/TRANSFER)
            // (Hanya untuk Admin Keuangan)
            // ----------------------------------------------------
            } elseif ($this->activeTab === 'proses_pencairan' && $this->isKeuangan) {
                $query = FundSubmission::with(['user', 'unit', 'disbursements' => function($q) {
                        $q->orderBy('termin_ke', 'asc');
                    }, 'disbursements.pencair'])
                    ->where('periode_id', $selectedPeriode->id)
                    ->where('status_pengajuan', 'approved')
                    ->whereHas('disbursements');

                if ($this->filterTipe) {
                    $query->where('tipe_pengajuan', $this->filterTipe);
                }

                if ($this->search) {
                    $query->where(function($q) {
                        $q->where('keperluan', 'like', '%' . $this->search . '%')
                          ->orWhereHas('user', function($qu) { $qu->where('name', 'like', '%' . $this->search . '%'); });
                    });
                }

                $query->orderByRaw("(SELECT MIN(FIELD(status_cair, 'pending', 'diproses', 'cair')) FROM fund_disbursements WHERE fund_disbursements.fund_submission_id = fund_submissions.id)");

                $pencairanSubmissions = $query->paginate(10);

            // ----------------------------------------------------
            // DATA TAB 3: VERIFIKASI LPJ & NOTA DARI DOSEN
            // ----------------------------------------------------
            } elseif ($this->activeTab === 'verifikasi_lpj') {
                $query = FundDisbursement::with(['submission.user', 'submission.unit', 'verifikatorLpj'])
                    ->whereHas('submission', function($q) use ($selectedPeriode, $headedUnitIds, $user) {
                        $q->where('periode_id', $selectedPeriode->id)->where('status_pengajuan', 'approved');
                        if (!$this->isKeuangan) {
                            $q->whereIn('unit_id', $headedUnitIds)->where('user_id', '!=', $user->id);
                        }

                        if ($this->filterTipe) {
                            $q->where('tipe_pengajuan', $this->filterTipe);
                        }
                    })
                    ->where('status_cair', 'cair') 
                    ->where(function($q) {
                        $q->whereIn('status_lpj', ['menunggu_verifikasi', 'selesai'])
                          ->orWhere(function($subQ) {
                              $subQ->where('status_lpj', 'belum')->whereNotNull('catatan_revisi_lpj');
                          });
                    });

                if ($this->filterStatus && $this->filterStatus !== 'semua') {
                    if ($this->filterStatus === 'revisi') {
                        $query->where('status_lpj', 'belum')->whereNotNull('catatan_revisi_lpj');
                    } else {
                        $query->where('status_lpj', $this->filterStatus);
                    }
                }

                if ($this->search) {
                    $query->whereHas('submission', function($q) {
                        $q->where('keperluan', 'like', '%' . $this->search . '%')
                          ->orWhereHas('user', function($qu) { $qu->where('name', 'like', '%' . $this->search . '%'); });
                    });
                }

                $lpjVerifications = $query->orderByRaw("FIELD(status_lpj, 'menunggu_verifikasi', 'belum', 'selesai')")
                                          ->latest('updated_at')
                                          ->paginate(10);
            }
        }

        return [
            'submissions' => $submissions,
            'pencairanSubmissions' => $pencairanSubmissions,
            'lpjVerifications' => $lpjVerifications,
            'allPeriodes' => $allPeriodes,
            'selectedPeriode' => $selectedPeriode,
        ];
    }
}; ?>

<div class="space-y-6 relative max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
        <div class="flex-1">
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight uppercase">Verifikasi Keuangan</h1>
            <p class="text-sm text-gray-500 mt-1">Satu portal untuk menyetujui anggaran, mencairkan dana, dan mengaudit LPJ.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <div class="w-full sm:w-64">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Periode Kinerja</label>
                <select wire:model.live="selectedPeriodeId" class="w-full border-gray-300 bg-gray-50 rounded-xl text-sm font-bold text-gray-900 focus:ring-blue-600 focus:border-blue-600 shadow-sm cursor-pointer transition-all">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($allPeriodes as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_periode }} @if($p->is_current) (Aktif) @endif</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm font-bold flex items-center gap-2 shadow-sm">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-bold flex items-center gap-2 shadow-sm">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    @if($selectedPeriode)
    
    <!-- Tab Navigasi Meja Kerja -->
    <div class="flex overflow-x-auto bg-white rounded-2xl border border-gray-200 shadow-sm p-1.5 mb-4 hide-scrollbar">
        <button wire:click="setTab('verifikasi_proposal')" class="flex-1 whitespace-nowrap px-4 py-3 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 {{ $activeTab === 'verifikasi_proposal' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            1. Meja Persetujuan
        </button>
        
        <!-- HANYA BAGIAN KEUANGAN YANG BISA MENTRANSFER DANA -->
        @if($isKeuangan)
        <button wire:click="setTab('proses_pencairan')" class="flex-1 whitespace-nowrap px-4 py-3 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 {{ $activeTab === 'proses_pencairan' ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            2. Antrean Pencairan
        </button>
        @endif
        
        <button wire:click="setTab('verifikasi_lpj')" class="flex-1 whitespace-nowrap px-4 py-3 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 {{ $activeTab === 'verifikasi_lpj' ? 'bg-emerald-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            3. Verifikasi LPJ
        </button>
    </div>

    <!-- Kotak Filter & Pencarian Universal -->
    <div class="bg-white p-4 rounded-2xl border border-gray-200 shadow-sm flex flex-col md:flex-row items-center gap-3">
        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterTipe" class="block w-full border border-gray-300 bg-gray-50 rounded-xl py-2.5 px-3 text-sm focus:ring-indigo-600 focus:border-indigo-600 text-gray-900 font-medium">
                <option value="">Semua Tipe</option>
                <option value="pribadi">👤 Pribadi</option>
                <option value="lembaga">🏢 Lembaga (Unit)</option>
            </select>
        </div>

        @if($activeTab === 'verifikasi_proposal')
            <div class="w-full md:w-auto flex-shrink-0">
                <select wire:model.live="filterStatus" class="block w-full border border-gray-300 bg-gray-50 rounded-xl py-2.5 px-3 text-sm focus:ring-blue-600 focus:border-blue-600 text-gray-900 font-medium">
                    <option value="">Semua Status Pengajuan</option>
                    <option value="pending">⏳ Menunggu Persetujuan</option>
                    <option value="approved">✅ Telah Disetujui</option>
                    <option value="rejected">❌ Telah Ditolak</option>
                </select>
            </div>
        @elseif($activeTab === 'verifikasi_lpj')
            <div class="w-full md:w-auto flex-shrink-0">
                <select wire:model.live="filterStatus" class="block w-full border border-gray-300 bg-gray-50 rounded-xl py-2.5 px-3 text-sm focus:ring-emerald-600 focus:border-emerald-600 text-gray-900 font-medium">
                    <option value="menunggu_verifikasi">⏳ Perlu Dicek (Menunggu)</option>
                    <option value="revisi">❌ Sedang Direvisi Pengaju</option>
                    <option value="selesai">✅ LPJ Selesai (Clear)</option>
                    <option value="semua">📂 Semua Data LPJ</option>
                </select>
            </div>
        @endif

        <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama pengaju atau rincian keperluan..." class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 bg-gray-50 rounded-xl focus:ring-indigo-600 focus:border-indigo-600 text-sm text-gray-900 transition-all">
        </div>
    </div>

    <!-- ============================================== -->
    <!-- TAB 1: MEJA PERSETUJUAN PROPOSAL               -->
    <!-- ============================================== -->
    @if($activeTab === 'verifikasi_proposal')
    <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest w-48">Pengaju & Tanggal</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Detail Keperluan</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Nominal Keuangan</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center w-36">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($submissions as $item)
                        <tr class="hover:bg-gray-50/50 transition-colors align-top" wire:key="verify-{{ $item->id }}">
                            <td class="px-6 py-5">
                                <div class="text-sm font-bold text-gray-900">{{ $item->user->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $item->created_at->translatedFormat('d M Y, H:i') }}</div>
                                @if($item->tipe_pengajuan === 'lembaga')
                                    <div class="mt-2 text-[9px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded w-max">
                                        Lembaga: {{ $item->unit ? ($item->unit->kode_unit ?? $item->unit->nama_unit) : '-' }}
                                    </div>
                                @else
                                    <div class="mt-2 text-[9px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded w-max">
                                        Pribadi
                                    </div>
                                @endif
                            </td>
                            
                            <td class="px-6 py-5">
                                <p class="text-sm font-medium text-gray-800 leading-relaxed">{{ Str::limit($item->keperluan, 100) }}</p>
                                @if($item->file_lampiran)
                                    <a href="{{ Storage::url($item->file_lampiran) }}" target="_blank" class="mt-2 inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-primary hover:text-primary-hover hover:underline transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                        Buka Proposal / RAB
                                    </a>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-right">
                                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Diajukan:</div>
                                <div class="text-sm font-bold text-gray-600 line-through">
                                    Rp {{ number_format($item->nominal_total, 0, ',', '.') }}
                                </div>
                                
                                @if($item->status_pengajuan === 'approved')
                                    <div class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest mt-1.5">Di-ACC:</div>
                                    <div class="text-base font-extrabold text-emerald-700">
                                        Rp {{ number_format($item->nominal_disetujui, 0, ',', '.') }}
                                    </div>
                                    <div class="mt-1">
                                        <span class="inline-flex px-1.5 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded text-[9px] font-bold uppercase tracking-widest">
                                            {{ $item->skema_pencairan === 'termin' ? $item->disbursements->count().' Termin' : 'Lumpsum' }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-center">
                                @if($item->status_pengajuan === 'pending')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200">
                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                        Pending
                                    </span>
                                @elseif($item->status_pengajuan === 'approved')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Disetujui
                                    </span>
                                    <p class="text-[9px] text-gray-400 mt-1 uppercase tracking-widest">Oleh: {{ $item->verifikator->name ?? 'Sistem' }}</p>
                                @elseif($item->status_pengajuan === 'rejected')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-700 border border-red-200">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Ditolak
                                    </span>
                                    <p class="text-[9px] text-gray-400 mt-1 uppercase tracking-widest">Oleh: {{ $item->verifikator->name ?? 'Sistem' }}</p>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-right">
                                @if($selectedPeriode->status !== 'closed')
                                    <button wire:click="openModal({{ $item->id }})" class="inline-flex items-center gap-1.5 px-4 py-2 {{ $item->status_pengajuan === 'pending' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200' }} text-xs font-bold rounded-xl shadow-sm transition-all" title="{{ $item->status_pengajuan === 'pending' ? 'Lakukan Verifikasi' : 'Ubah Keputusan Verifikasi' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if($item->status_pengajuan === 'pending')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                            @endif
                                        </svg>
                                        {{ $item->status_pengajuan === 'pending' ? 'Verifikasi' : 'Edit' }}
                                    </button>
                                @else
                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Terkunci</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-16 text-center text-gray-500">Belum ada proposal masuk untuk diverifikasi pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($submissions->hasPages())<div class="px-6 py-4 border-t border-gray-200">{{ $submissions->links() }}</div>@endif
    </div>
    
    <!-- ============================================== -->
    <!-- TAB 2: MEJA ANTREAN PENCAIRAN (TRANSFER)       -->
    <!-- ============================================== -->
    @elseif($activeTab === 'proses_pencairan' && $isKeuangan)
    <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest w-40">Info Termin</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Nominal Pencairan</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center w-36">Status Cair</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right w-48">Aksi Transfer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pencairanSubmissions as $sub)
                        <!-- Header Kelompok Pengajuan (Submission Induk) -->
                        <tr class="bg-indigo-50/40 border-t-2 border-indigo-100">
                            <td colspan="4" class="px-6 py-4">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <span class="text-sm font-bold text-indigo-900">{{ $sub->user->name ?? 'Unknown' }}</span>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $sub->tipe_pengajuan === 'lembaga' ? 'bg-blue-100 text-blue-700 border border-blue-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200' }}">
                                                {{ $sub->tipe_pengajuan === 'lembaga' ? ($sub->unit->kode_unit ?? $sub->unit->nama_unit) : 'Pribadi' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-indigo-800/80 font-medium leading-relaxed max-w-3xl">{{ Str::limit($sub->keperluan, 120) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] text-indigo-500 font-bold uppercase tracking-widest mb-0.5">Total Di-ACC</div>
                                        <div class="text-base font-black text-indigo-700">Rp {{ number_format($sub->nominal_disetujui, 0, ',', '.') }}</div>
                                        <div class="text-[9px] font-bold uppercase bg-white border border-indigo-200 text-indigo-500 px-1.5 py-0.5 rounded mt-1 inline-block shadow-sm">
                                            {{ $sub->skema_pencairan === 'termin' ? $sub->disbursements->count().' Termin' : 'Lumpsum' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Baris Anak (Termin per Pengajuan) -->
                        @foreach($sub->disbursements as $item)
                            <tr class="hover:bg-gray-50/50 transition-colors align-middle" wire:key="disb-{{ $item->id }}">
                                <td class="px-6 py-4 relative pl-12">
                                    <!-- Visual Tree Line -->
                                    <div class="absolute left-6 top-0 bottom-0 w-px bg-indigo-200 {{ $loop->last ? 'h-1/2' : '' }}"></div>
                                    <div class="absolute left-6 top-1/2 w-4 h-px bg-indigo-200"></div>
                                    
                                    <span class="inline-flex px-2.5 py-1 bg-white border border-indigo-200 text-indigo-700 rounded-lg text-[10px] font-bold uppercase tracking-widest relative z-10 shadow-sm">
                                        Termin {{ $item->termin_ke }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <div class="text-sm font-black text-gray-900">Rp {{ number_format($item->nominal_cair, 0, ',', '.') }}</div>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    @if($item->status_cair === 'cair')
                                        <div class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-bold uppercase bg-emerald-50 text-emerald-600 border border-emerald-200 shadow-sm mb-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Telah Cair
                                        </div>
                                        <p class="text-[9px] text-gray-400 uppercase tracking-widest mt-1">Oleh: {{ $item->pencair->name ?? 'Sistem' }}</p>
                                    @else
                                        <div class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-bold uppercase bg-amber-50 text-amber-600 border border-amber-200 shadow-sm">
                                            <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span> Pending
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-right">
                                    @php
                                        // LOGIKA PINTAR: Termin 2 tidak bisa dicairkan jika LPJ termin 1 belum selesai
                                        $isLockedByPrevious = false;
                                        if ($item->termin_ke > 1) {
                                            $previousTermin = $sub->disbursements->where('termin_ke', $item->termin_ke - 1)->first();
                                            if ($previousTermin && $previousTermin->status_lpj !== 'selesai') {
                                                $isLockedByPrevious = true;
                                            }
                                        }
                                    @endphp

                                    @if($item->status_cair !== 'cair')
                                        @if($isLockedByPrevious)
                                            <div class="flex flex-col items-end gap-1">
                                                <span class="text-[10px] text-red-500 font-bold uppercase tracking-widest text-right leading-tight">Terkunci!</span>
                                                <span class="text-[9px] text-gray-400 text-right">LPJ Termin ke-{{ $item->termin_ke - 1 }} belum diselesaikan.</span>
                                            </div>
                                        @else
                                            <button wire:click="openPencairanModal({{ $item->id }})" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-md">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg> Tandai Cair
                                            </button>
                                        @endif
                                    @else
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="openPencairanModal({{ $item->id }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-[10px] font-bold uppercase rounded-lg hover:border-indigo-500 hover:text-indigo-600 transition-all shadow-sm">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg> Edit Bukti
                                            </button>
                                        </div>
                                        @if($item->bukti_transfer_kampus)
                                            <div class="mt-2 text-right">
                                                <a href="{{ Storage::url($item->bukti_transfer_kampus) }}" target="_blank" class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider text-blue-600 hover:underline">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> Lihat Transfer
                                                </a>
                                            </div>
                                        @endif
                                        @if($item->status_lpj === 'belum')
                                            <button wire:click="cancelPencairan({{ $item->id }})" wire:confirm="Batalkan status pencairan dana ini dan kembali ke pending?" class="mt-2 text-[9px] text-red-500 hover:text-red-700 underline font-bold uppercase tracking-wider flex items-center justify-end w-full gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Batalkan Pencairan
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="4" class="px-6 py-16 text-center text-gray-500">Tidak ada jadwal termin pencairan yang tertunda pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pencairanSubmissions->hasPages())<div class="px-6 py-4 border-t border-gray-200">{{ $pencairanSubmissions->links() }}</div>@endif
    </div>

    <!-- ============================================== -->
    <!-- TAB 3: VERIFIKASI LPJ & SISA DANA              -->
    <!-- ============================================== -->
    @elseif($activeTab === 'verifikasi_lpj')
    <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[950px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest w-1/3">Pengaju & Kegiatan</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Dana Cair Termin</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Realisasi & Sisa (SiLPA)</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center">Status LPJ</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right w-36">Aksi Audit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lpjVerifications as $item)
                        <tr class="hover:bg-gray-50/50 transition-colors align-top" wire:key="lpj-{{ $item->id }}">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="text-sm font-bold text-gray-900">{{ $item->submission->user->name ?? 'Unknown' }}</span>
                                    <span class="inline-flex px-1.5 py-0.5 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded text-[9px] font-bold uppercase tracking-widest">Termin {{ $item->termin_ke }}</span>
                                </div>
                                <p class="text-xs text-gray-600 font-medium leading-relaxed">{{ Str::limit($item->submission->keperluan, 80) }}</p>
                            </td>

                            <td class="px-6 py-5 text-right">
                                <span class="text-sm font-bold text-gray-500">Rp {{ number_format($item->nominal_cair, 0, ',', '.') }}</span>
                            </td>

                            <td class="px-6 py-5 text-right">
                                @if($item->nominal_realisasi)
                                    <span class="text-sm font-extrabold text-emerald-600">Rp {{ number_format($item->nominal_realisasi, 0, ',', '.') }}</span>
                                    @if($item->nominal_kembali > 0)
                                        <div class="mt-1 text-[10px] font-bold tracking-wide text-amber-600">Sisa: Rp {{ number_format($item->nominal_kembali, 0, ',', '.') }}</div>
                                    @else
                                        <div class="mt-1 text-[10px] font-bold tracking-wide text-gray-500">Dana Habis Terserap</div>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400 italic">Belum dilaporkan</span>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-center">
                                @if($item->status_lpj === 'menunggu_verifikasi')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-wider border border-amber-200">
                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span> Pending
                                    </span>
                                @elseif($item->status_lpj === 'selesai')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-wider border border-emerald-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Selesai
                                    </span>
                                    <p class="text-[9px] text-gray-400 uppercase tracking-widest mt-1">Oleh: {{ $item->verifikatorLpj->name ?? 'Sistem' }}</p>
                                @elseif($item->status_lpj === 'belum' && $item->catatan_revisi_lpj)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-red-50 text-red-600 text-[10px] font-bold uppercase tracking-wider border border-red-200">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Direvisi
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-right">
                                @if($item->status_lpj === 'menunggu_verifikasi')
                                    <button wire:click="openLpjModal({{ $item->id }})" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 shadow-md transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Audit LPJ
                                    </button>
                                @else
                                    <button wire:click="openLpjModal({{ $item->id }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-amber-500 text-amber-600 text-xs font-bold rounded-lg hover:bg-amber-50 shadow-sm transition-all" title="Buka kembali untuk direvisi">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        Edit Status
                                    </button>
                                    @if($item->status_lpj === 'selesai')
                                        <button wire:click="cancelLpjVerification({{ $item->id }})" wire:confirm="Batalkan status Selesai pada LPJ ini?" class="mt-2 text-[9px] text-amber-600 hover:text-amber-800 underline font-bold uppercase tracking-wider flex items-center justify-end w-full gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Batal Verifikasi
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-16 text-center text-gray-500">Tidak ada LPJ yang perlu diverifikasi pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lpjVerifications->hasPages())<div class="px-6 py-4 border-t border-gray-200">{{ $lpjVerifications->links() }}</div>@endif
    </div>
    @endif
    @endif <!-- END TABS -->

    <!-- ========================================== -->
    <!-- MODAL 1: VERIFIKASI PROPOSAL (TAB 1) -->
    <!-- ========================================== -->
    @if($isModalOpen && $selectedSubmission)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center shrink-0">
                    <h3 class="text-lg font-bold text-gray-900 leading-tight">Proses Verifikasi Pengajuan Proposal</h3>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-gray-400 hover:text-red-500 transition-colors p-2 bg-white rounded-lg border border-gray-200 shadow-sm hover:bg-red-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="flex flex-col overflow-hidden flex-1">
                    <div class="p-6 overflow-y-auto custom-scrollbar">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            
                            <!-- KIRI: Info Proposal -->
                            <div class="space-y-5">
                                <div>
                                    <h4 class="text-xs font-black text-gray-800 uppercase tracking-widest border-b border-gray-200 pb-2 mb-4 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Informasi Proposal
                                    </h4>
                                    <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100 mb-4">
                                        <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wider mb-1">Total Dana Diajukan</p>
                                        <p class="text-2xl font-black text-gray-900">Rp {{ number_format($selectedSubmission->nominal_total, 0, ',', '.') }}</p>
                                        <p class="text-[10px] text-gray-500 mt-1">Skema Permintaan: <strong class="text-gray-700 uppercase">{{ $selectedSubmission->skema_pencairan }}</strong></p>
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Pengaju / Unit</p>
                                            <p class="text-sm font-bold text-gray-900">{{ $selectedSubmission->user->name }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Keperluan</p>
                                            <p class="text-sm text-gray-800 leading-relaxed bg-white p-3 rounded-xl border border-gray-200 shadow-sm">{{ $selectedSubmission->keperluan }}</p>
                                        </div>
                                        @if($selectedSubmission->file_lampiran)
                                            <a href="{{ Storage::url($selectedSubmission->file_lampiran) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-700 hover:text-blue-600 hover:border-blue-500 transition-all shadow-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg> Buka Proposal (PDF)
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- KANAN: Keputusan & Skema -->
                            <div class="space-y-6">
                                
                                <!-- KEPUTUSAN AKHIR (DI ATAS AGAR LOGIS) -->
                                <div>
                                    <h4 class="text-xs font-black text-gray-800 uppercase tracking-widest border-b border-gray-200 pb-2 mb-4 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                        Keputusan Akhir
                                    </h4>
                                    <div class="grid grid-cols-2 gap-3 mb-4">
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="actionStatus" value="approved" class="hidden peer">
                                            <div class="p-3 text-center rounded-xl border border-gray-300 bg-white peer-checked:bg-emerald-600 peer-checked:border-emerald-600 peer-checked:text-white transition-all shadow-sm">
                                                <span class="text-sm font-bold block mb-1">✅ Setujui (ACC)</span>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" wire:model.live="actionStatus" value="rejected" class="hidden peer">
                                            <div class="p-3 text-center rounded-xl border border-gray-300 bg-white peer-checked:bg-red-600 peer-checked:border-red-600 peer-checked:text-white transition-all shadow-sm">
                                                <span class="text-sm font-bold block mb-1">❌ Tolak / Revisi</span>
                                            </div>
                                        </label>
                                    </div>
                                    @error('actionStatus') <span class="text-xs text-red-500 block font-medium mb-3">{{ $message }}</span> @enderror
                                </div>

                                <!-- TINJAUAN & SKEMA (MUNCUL JIKA DISETUJUI) -->
                                @if($actionStatus === 'approved')
                                    <div class="animate-fade-in-up">
                                        <h4 class="text-xs font-black text-gray-800 uppercase tracking-widest border-b border-gray-200 pb-2 mb-4 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                                            Tinjauan & Skema Dana
                                        </h4>
                                        <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200 space-y-4 shadow-inner">
                                            <div>
                                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nominal Akhir Disetujui (Rp)</label>
                                                <div class="relative">
                                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold">Rp</span>
                                                    <input type="number" wire:model.live.debounce.500ms="nominal_disetujui" min="0" class="block w-full pl-9 pr-3 py-2 border border-gray-300 bg-white rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-sm text-gray-900 font-bold shadow-sm">
                                                </div>
                                                @error('nominal_disetujui') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                                            </div>
                                            
                                            <div class="pt-3 border-t border-gray-200">
                                                <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-2">Metode Pencairan</label>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <label class="cursor-pointer">
                                                        <input type="radio" wire:model.live="skema_pencairan" value="lumpsum" class="hidden peer">
                                                        <div class="p-2.5 text-center rounded-xl border border-gray-300 bg-white peer-checked:bg-indigo-100 peer-checked:border-indigo-500 peer-checked:text-indigo-700 transition-all text-xs font-bold shadow-sm">Lumpsum (1x Cair)</div>
                                                    </label>
                                                    <label class="cursor-pointer">
                                                        <input type="radio" wire:model.live="skema_pencairan" value="termin" class="hidden peer">
                                                        <div class="p-2.5 text-center rounded-xl border border-gray-300 bg-white peer-checked:bg-indigo-100 peer-checked:border-indigo-500 peer-checked:text-indigo-700 transition-all text-xs font-bold shadow-sm">Termin (Bertahap)</div>
                                                    </label>
                                                </div>
                                            </div>

                                            @if($skema_pencairan === 'termin')
                                                <div class="pt-3 border-t border-gray-200">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <p class="text-xs font-bold text-gray-800">Jumlah Termin</p>
                                                        <input type="number" wire:model.live.debounce.500ms="jumlah_termin" min="2" max="24" class="block w-24 border-gray-300 rounded-lg text-sm font-bold text-center focus:ring-indigo-500 py-1.5 shadow-sm">
                                                    </div>
                                                    @error('jumlah_termin') <span class="text-xs text-red-500 mb-2 block font-medium">{{ $message }}</span> @enderror
                                                    
                                                    @if((int)$jumlah_termin >= 2)
                                                    <div class="space-y-2">
                                                        @for($i=1; $i<=$jumlah_termin; $i++)
                                                            <div class="flex items-center gap-3">
                                                                <span class="text-xs font-bold text-gray-600 w-16">Termin {{ $i }}</span>
                                                                <div class="relative flex-1">
                                                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-xs font-bold">Rp</span>
                                                                    <input type="number" wire:model.live.debounce.500ms="termin_nominals.{{ $i }}" class="block w-full pl-9 pr-3 py-1.5 border border-gray-300 rounded-lg text-sm font-bold focus:ring-indigo-500 shadow-sm">
                                                                </div>
                                                            </div>
                                                        @endfor
                                                        <div class="mt-3 p-2 rounded-lg text-xs font-bold text-center border transition-colors {{ $total_input_termin == $nominal_disetujui ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200' }}">
                                                            Total Alokasi: Rp {{ number_format($total_input_termin, 0, ',', '.') }} 
                                                        </div>
                                                    </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                <!-- CATATAN MUNCUL BERDASARKAN STATUS -->
                                @if($actionStatus === 'rejected')
                                    <div class="animate-fade-in-up">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Catatan Penolakan <span class="text-red-500 normal-case font-normal">(Wajib)</span></label>
                                        <textarea wire:model="catatan" rows="3" class="block w-full border border-red-300 bg-red-50 rounded-xl py-2.5 px-3 text-sm focus:ring-red-500 focus:border-red-500 shadow-sm placeholder-red-300" placeholder="Berikan alasan mengapa pengajuan ini ditolak/revisi..."></textarea>
                                        @error('catatan') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                                    </div>
                                @elseif($actionStatus === 'approved')
                                    <div class="animate-fade-in-up">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Catatan Tambahan <span class="text-gray-400 normal-case font-normal">(Opsional)</span></label>
                                        <textarea wire:model="catatan" rows="2" class="block w-full border border-gray-300 bg-white rounded-xl py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500 shadow-sm" placeholder="Berikan pesan singkat untuk pengaju jika diperlukan..."></textarea>
                                    </div>
                                @endif
                                
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-gray-500 bg-white border border-gray-200 rounded-xl shadow-sm">Batal</button>
                        <button type="button" wire:click="saveVerification" wire:loading.attr="disabled" class="px-6 py-2.5 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md flex items-center gap-2">Simpan Keputusan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 2: EKSEKUSI PENCAIRAN (TAB 2) -->
    <!-- ========================================== -->
    @if($isPencairanModalOpen && $selectedDisbursement)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full max-w-lg overflow-hidden flex flex-col" onclick="event.stopPropagation()">
                <div class="px-6 py-5 border-b border-indigo-100 bg-indigo-50/50 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-indigo-900 leading-tight">Proses Pencairan Dana</h3>
                        <p class="text-[10px] text-indigo-600 font-bold uppercase tracking-widest mt-1">Termin {{ $selectedDisbursement->termin_ke }} dari {{ $selectedDisbursement->submission->skema_pencairan }}</p>
                    </div>
                    <button type="button" wire:click="$set('isPencairanModalOpen', false)" class="text-indigo-400 hover:text-red-500 transition-colors p-2 bg-white rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="prosesPencairan" class="flex flex-col">
                    <div class="p-6 space-y-6">
                        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5 text-center shadow-inner">
                            <p class="text-[10px] text-indigo-600 font-bold uppercase tracking-widest mb-1">Nominal Ditransfer</p>
                            <p class="text-3xl font-black text-indigo-900">Rp {{ number_format($selectedDisbursement->nominal_cair, 0, ',', '.') }}</p>
                            <p class="text-xs text-indigo-700 font-medium mt-2">Kepada: <strong>{{ $selectedDisbursement->submission->user->name }}</strong></p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1.5">Tanggal Dicairkan / Transfer <span class="text-red-500">*</span></label>
                                <input type="date" wire:model="tanggal_cair" class="block w-full border-gray-300 rounded-xl text-sm focus:ring-indigo-500 bg-white shadow-sm font-medium">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1.5">Upload Bukti Transfer <span class="text-red-500">*</span></label>
                                <div class="border-2 border-dashed border-gray-300 rounded-2xl p-4 text-center bg-white relative hover:bg-gray-50">
                                    <input type="file" wire:model="bukti_transfer_kampus" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                    <div class="pointer-events-none">
                                        <p class="text-sm font-bold text-indigo-600">Klik untuk memilih file bukti transfer</p>
                                    </div>
                                </div>
                                <div wire:loading wire:target="bukti_transfer_kampus" class="text-[10px] font-bold text-indigo-600 mt-2">Mengunggah file...</div>
                                @error('bukti_transfer_kampus') <span class="text-xs text-red-500 mt-2 block font-bold">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isPencairanModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-gray-500 bg-white border border-gray-200 rounded-xl shadow-sm">Batal</button>
                        <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl shadow-md">Tandai Sudah Cair</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL 3: VERIFIKASI LPJ & SISA DANA (TAB 3) -->
    <!-- ========================================== -->
    @if($isLpjModalOpen && $selectedLpj)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center shrink-0">
                    <h3 class="text-lg font-bold text-gray-900">Audit Laporan Pertanggungjawaban (LPJ)</h3>
                    @if($selectedLpj->status_lpj === 'selesai')
                        <span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase border border-emerald-200">Status: Selesai</span>
                    @endif
                </div>
                
                <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar">
                    @if($selectedLpj->status_lpj === 'selesai')
                        <div class="bg-amber-50 p-4 rounded-xl border border-amber-200 text-xs text-amber-800 font-medium">
                            LPJ ini sudah dikunci sebagai <strong>Selesai</strong>. Jika Anda mengubah status menjadi revisi, Anda akan mengunci pencairan termin berikutnya (jika ada).
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
                            <p class="text-[10px] text-indigo-600 font-bold uppercase tracking-widest mb-1">Dana Ditransfer (Awal)</p>
                            <p class="text-xl font-black text-gray-900">Rp {{ number_format($selectedLpj->nominal_cair, 0, ',', '.') }}</p>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-2xl border border-blue-200">
                            <p class="text-[10px] text-blue-600 font-bold uppercase tracking-widest mb-1">Realisasi Dilaporkan Dosen</p>
                            <p class="text-xl font-black text-gray-900">Rp {{ number_format($selectedLpj->nominal_realisasi, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    @if($selectedLpj->nominal_kembali > 0)
                        <div class="bg-amber-50 border border-amber-200 p-4 rounded-2xl flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black text-amber-800 uppercase tracking-widest mb-0.5">Sisa Dana (SiLPA) Dikembalikan</p>
                                <p class="text-lg font-black text-amber-600">Rp {{ number_format($selectedLpj->nominal_kembali, 0, ',', '.') }}</p>
                            </div>
                            @if($selectedLpj->bukti_pengembalian)
                                <a href="{{ Storage::url($selectedLpj->bukti_pengembalian) }}" target="_blank" class="px-4 py-2 bg-white text-amber-700 text-xs font-bold border border-amber-200 rounded-lg hover:border-amber-400">
                                    Cek Bukti Transfer 
                                </a>
                            @else
                                <span class="text-xs text-red-500 font-bold bg-white px-2 py-1 rounded border border-red-200">Bukti Belum Diupload!</span>
                            @endif
                        </div>
                        
                        <div class="text-[10px] text-amber-700 font-medium bg-white p-3 rounded-xl border border-amber-100 mt-2">
                            <span class="font-bold uppercase tracking-widest block mb-1">Verifikasi Sisa Dana:</span>
                            Pastikan Anda telah mengecek mutasi rekening kampus. Menekan tombol "Sesuai & Selesai" berarti Anda mengonfirmasi bahwa dana SiLPA ini <strong>telah masuk ke rekening kampus</strong> dengan jumlah yang sesuai.
                        </div>
                    @endif

                    <div class="text-center py-2 border-y border-gray-100">
                        @if($selectedLpj->file_lpj)
                            <a href="{{ Storage::url($selectedLpj->file_lpj) }}" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 font-bold text-sm rounded-xl transition-all shadow-sm"> 
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                Buka File LPJ & Kuitansi (Tab Baru)
                            </a>
                        @else
                            <div class="text-sm text-red-500 font-bold bg-red-50 p-3 rounded-lg border border-red-200">Dosen belum menyertakan file lampiran bukti!</div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-2">Catatan Revisi <span class="text-red-500 font-normal italic">(Wajib diisi jika ditolak)</span></label>
                        <textarea wire:model="catatanLpj" rows="3" class="block w-full border border-gray-300 bg-white rounded-xl py-2.5 px-3 text-sm focus:ring-emerald-500 shadow-sm" placeholder="Contoh: Kuitansi konsumsi belum ditandatangani..."></textarea>
                        @error('catatanLpj') <span class="text-xs text-red-500 font-medium mt-1.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 shrink-0">
                    <button type="button" wire:click="$set('isLpjModalOpen', false)" class="px-4 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-900 transition-colors">Tutup</button>
                    
                    <div class="flex gap-2">
                        <button type="button" wire:click="rejectLpj" class="px-4 py-2.5 text-sm font-bold text-red-600 bg-white border border-red-200 hover:bg-red-50 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            {{ $selectedLpj->status_lpj === 'selesai' ? 'Batalkan (Minta Revisi)' : 'Tolak & Revisi' }}
                        </button>
                        
                        @if($selectedLpj->status_lpj !== 'selesai')
                            <button type="button" wire:click="approveLpj" class="px-5 py-2.5 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md transition-all flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Sesuai & Selesai
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>