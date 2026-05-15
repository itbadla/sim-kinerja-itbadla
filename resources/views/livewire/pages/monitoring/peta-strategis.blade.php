<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Unit;
use App\Models\WorkProgram;
use App\Models\PerformanceIndicator;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {
    public $filterUnit = '';
    public $filterStatus = '';
    
    public $units = [];
    public $graphData = ['nodes' => [], 'edges' => []];
    public $nodeDetails = []; // Menyimpan detail lengkap untuk Modal Popup

    public function mount()
    {
        // Ambil daftar unit untuk dropdown
        $this->units = Unit::orderBy('nama_unit')->get();
        $this->generateGraphData();
    }

    // Trigger saat dropdown diubah
    public function updatedFilterUnit()
    {
        $this->generateGraphData(true);
    }

    public function updatedFilterStatus()
    {
        $this->generateGraphData(true);
    }

    public function generateGraphData($dispatch = false)
    {
        $nodes = [];
        $edges = [];
        $details = [];
        $indicatorIdsDibutuhkan = [];
        $indicatorProkersMap = []; // Menampung data proker untuk ditampilkan di modal IKU

        // 1. AMBIL DATA PROGRAM KERJA BERDASARKAN FILTER
        $prokerQuery = WorkProgram::with('unit');
        
        if ($this->filterUnit !== '') {
            $prokerQuery->where('unit_id', $this->filterUnit);
        }
        
        if ($this->filterStatus !== '') {
            $prokerQuery->where('status', $this->filterStatus);
        }

        $prokers = $prokerQuery->get();

        foreach ($prokers as $proker) {
            // Tentukan Warna berdasarkan Status
            $color = $proker->status === 'disetujui' ? '#10b981' : '#f59e0b'; // Emerald (Hijau) / Amber (Kuning)
            
            // Kalkulasi Keuangan Pengajuan
            $submissions = DB::table('fund_submissions')->where('work_program_id', $proker->id)->get();
            $totalDiajukan = $submissions->sum('nominal_total');
            $totalCair = $submissions->where('status_pengajuan', 'approved')->sum('nominal_disetujui');

            // Tambahkan Node Proker
            $nodes[] = [
                'id' => 'P-' . $proker->id,
                'label' => wordwrap($proker->nama_proker, 25, "\n"),
                'shape' => 'box',
                'color' => [
                    'background' => $color,
                    'border' => '#ffffff'
                ],
                'font' => ['color' => '#ffffff', 'size' => 12, 'bold' => true],
                'group' => 'proker'
            ];

            // Simpan Detail untuk Modal
            $details['P-' . $proker->id] = [
                'tipe' => 'Program Kerja',
                'judul' => $proker->nama_proker,
                'unit' => $proker->unit ? $proker->unit->nama_unit : '-',
                'status' => strtoupper($proker->status),
                'anggaran_rencana' => $proker->anggaran_rencana,
                'total_diajukan' => $totalDiajukan,
                'total_cair' => $totalCair,
                'deskripsi' => $proker->deskripsi ?? 'Tidak ada deskripsi.'
            ];

            // Cek Relasi Pivot IKU/IKT untuk Edge
            $relations = DB::table('work_program_indicators')->where('work_program_id', $proker->id)->get();
            foreach ($relations as $rel) {
                $indicatorIdsDibutuhkan[] = $rel->indicator_id;
                
                // Simpan data proker ini ke dalam map IKU
                if (!isset($indicatorProkersMap[$rel->indicator_id])) {
                    $indicatorProkersMap[$rel->indicator_id] = [];
                }
                $indicatorProkersMap[$rel->indicator_id][] = [
                    'id' => $proker->id,
                    'nama_proker' => $proker->nama_proker,
                    'unit' => $proker->unit ? $proker->unit->nama_unit : '-',
                    'status' => strtoupper($proker->status),
                    'anggaran_rencana' => $proker->anggaran_rencana,
                ];

                $edges[] = [
                    'from' => 'P-' . $proker->id,
                    'to' => 'I-' . $rel->indicator_id,
                    'label' => $rel->target_angka . ' ' . $rel->satuan_target,
                    'font' => ['align' => 'top', 'size' => 10, 'color' => '#6b7280'],
                    'color' => ['color' => '#cbd5e1', 'highlight' => '#3b82f6'],
                    'arrows' => 'to',
                    'smooth' => ['type' => 'curvedCW', 'roundness' => 0.2]
                ];
            }
        }

        // 2. AMBIL DATA INDIKATOR (Hanya yang terhubung dengan Proker yang difilter)
        $indicatorIdsDibutuhkan = array_unique($indicatorIdsDibutuhkan);
        if (!empty($indicatorIdsDibutuhkan)) {
            $indicators = PerformanceIndicator::whereIn('id', $indicatorIdsDibutuhkan)->get();
            
            foreach ($indicators as $ind) {
                $nodes[] = [
                    'id' => 'I-' . $ind->id,
                    'label' => $ind->kode_indikator . "\n" . wordwrap($ind->nama_indikator, 25, "\n"),
                    'shape' => 'box',
                    'color' => [
                        'background' => '#6366f1', // Indigo/Ungu
                        'border' => '#4f46e5'
                    ],
                    'font' => ['color' => '#ffffff', 'size' => 14, 'bold' => true],
                    'group' => 'indikator'
                ];

                $details['I-' . $ind->id] = [
                    'tipe' => 'Indikator Kinerja (' . $ind->kategori . ')',
                    'judul' => $ind->kode_indikator . ' - ' . $ind->nama_indikator,
                    'unit' => 'Target Institusi',
                    'status' => 'MASTER DATA',
                    'deskripsi' => 'Indikator ini wajib dipenuhi oleh proker terkait.',
                    'connected_prokers' => $indicatorProkersMap[$ind->id] ?? [] // Kirim data proker terkait
                ];
            }
        }

        $this->graphData = [
            'nodes' => array_values($nodes),
            'edges' => array_values($edges)
        ];
        $this->nodeDetails = $details;

        // 3. JIKA DIPANGGIL DARI FILTER, DISPATCH KE BROWSER
        if ($dispatch) {
            $this->dispatch('update-network-data', [
                'nodes' => $this->graphData['nodes'],
                'edges' => $this->graphData['edges'],
                'details' => $this->nodeDetails
            ]);
        }
    }
}; ?>

<div class="space-y-6 pb-10" x-data="networkGraph()">
    <!-- Memuat Library Vis Network via CDN secara langsung agar bisa mandiri -->
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Peta Strategis</h1>
            <p class="text-sm text-theme-muted mt-1">Visualisasi hubungan antara Program Kerja dan Target IKU/IKT Institusi.</p>
        </div>
        
        <!-- Filter Controls -->
        <div class="flex items-center gap-3">
            <select wire:model.live="filterUnit" class="bg-theme-surface border border-theme-border text-theme-text text-sm rounded-xl px-4 py-2 focus:ring-primary focus:border-primary">
                <option value="">-- Semua Unit/Fakultas --</option>
                @foreach($units as $u)
                    <option value="{{ $u->id }}">{{ $u->nama_unit }}</option>
                @endforeach
            </select>
            
            <select wire:model.live="filterStatus" class="bg-theme-surface border border-theme-border text-theme-text text-sm rounded-xl px-4 py-2 focus:ring-primary focus:border-primary">
                <option value="">-- Semua Status --</option>
                <option value="disetujui">Disetujui (Berjalan)</option>
                <option value="review_lpm">Dalam Reviu</option>
                <option value="draft">Draft / Perencanaan</option>
            </select>
        </div>
    </div>

    <!-- KETERANGAN WARNA -->
    <div class="flex flex-wrap gap-4 text-xs font-bold text-theme-muted bg-theme-surface py-3 px-5 rounded-2xl border border-theme-border">
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded bg-[#6366f1] border border-[#4f46e5]"></span> Indikator Kinerja (IKU/IKT)
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-[#10b981] border border-white"></span> Proker Disetujui
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-[#f59e0b] border border-white"></span> Proker Draft / Pengajuan
        </div>
        <div class="flex items-center gap-2 ml-auto text-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
            Klik pada item untuk melihat rincian anggaran
        </div>
    </div>

    <!-- WADAH CANVAS VIS-NETWORK -->
    <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-sm overflow-hidden relative">
        <!-- Canvas -->
        <div id="network-container" class="w-full h-[600px] bg-theme-body/30 cursor-grab active:cursor-grabbing"></div>
        
        <!-- LOADING OVERLAY (Tampil saat memproses posisi grafik) -->
        <div class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-theme-surface/60 backdrop-blur-sm transition-opacity duration-300" x-show="isCalculating">
            <div class="relative w-16 h-16">
                <div class="absolute inset-0 border-4 border-primary/20 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-primary rounded-full border-t-transparent animate-spin"></div>
            </div>
            <div class="text-center mt-3">
                <span class="px-3 py-1 bg-white dark:bg-gray-800 rounded-full text-xs font-bold text-gray-700 dark:text-gray-200 shadow-sm inline-flex items-center gap-2">
                    <svg class="w-3 h-3 text-emerald-500 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
                    Menyusun Tata Letak...
                </span>
            </div>
        </div>

        <!-- KONTROL ZOOM (SLIDER MELAYANG DI KANAN BAWAH) -->
        <div class="absolute bottom-6 right-6 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm border border-gray-200 dark:border-gray-700 rounded-2xl shadow-lg p-2 flex items-center gap-3 z-10 transition-all">
            <button @click="zoomOut" class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 transition-colors" title="Perkecil">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path></svg>
            </button>
            
            <div class="flex flex-col items-center gap-1">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest" x-text="Math.round(zoomLevel * 100) + '%'">100%</span>
                <input type="range" min="0.1" max="2.5" step="0.05" x-model="zoomLevel" @input="updateZoom" class="w-24 md:w-32 h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600 dark:accent-blue-400">
            </div>

            <button @click="zoomIn" class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 transition-colors" title="Perbesar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
            </button>
        </div>
    </div>

    <!-- MODAL DETAIL KLIK (Pengganti Tooltip) -->
    <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Background overlay -->
        <div x-show="showModal" x-transition.opacity class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>

        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <!-- Modal panel dengan Flexbox dan Batasan Tinggi -->
            <div x-show="showModal" x-transition.scale.origin.bottom class="relative flex flex-col max-h-[90vh] transform overflow-hidden rounded-3xl bg-theme-surface text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-theme-border">
                
                <template x-if="selectedData">
                    <div class="flex flex-col h-full overflow-hidden w-full">
                        <!-- Modal Header (Terkunci / Tidak ikut scroll) -->
                        <div class="bg-theme-body px-6 py-4 border-b border-theme-border flex items-start justify-between shrink-0">
                            <div>
                                <p class="text-[10px] font-bold text-primary uppercase tracking-widest mb-1" x-text="selectedData.tipe"></p>
                                <h3 class="text-lg font-extrabold text-theme-text leading-tight" x-text="selectedData.judul"></h3>
                            </div>
                            <button @click="showModal = false" class="text-theme-muted hover:text-rose-500 transition-colors p-1 bg-theme-surface rounded-xl">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <!-- Modal Body (Area yang bisa di-scroll) -->
                        <div class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                            <!-- Info Dasar -->
                            <div class="flex items-center justify-between p-3 bg-theme-body rounded-xl border border-theme-border shrink-0">
                                <div>
                                    <p class="text-[10px] text-theme-muted font-bold uppercase">Unit Pelaksana</p>
                                    <p class="text-sm font-bold text-theme-text" x-text="selectedData.unit"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-theme-muted font-bold uppercase">Status</p>
                                    <span class="inline-block px-2 py-1 text-[10px] font-bold rounded bg-theme-surface border border-theme-border text-theme-text" x-text="selectedData.status"></span>
                                </div>
                            </div>

                            <!-- Info Keuangan (Hanya Tampil Jika Ini Proker) -->
                            <template x-if="selectedData.anggaran_rencana !== undefined">
                                <div class="space-y-3 pt-2">
                                    <h4 class="text-xs font-bold text-theme-muted uppercase border-b border-theme-border pb-2">Informasi Keuangan</h4>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-semibold text-theme-text">Pagu Direncanakan:</span>
                                        <span class="text-sm font-extrabold text-theme-text" x-text="formatRupiah(selectedData.anggaran_rencana)"></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-semibold text-amber-600">Total Pengajuan:</span>
                                        <span class="text-sm font-extrabold text-amber-600" x-text="formatRupiah(selectedData.total_diajukan)"></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-semibold text-emerald-600">Dana Cair (Approved):</span>
                                        <span class="text-sm font-extrabold text-emerald-600" x-text="formatRupiah(selectedData.total_cair)"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Deskripsi -->
                            <div class="pt-2">
                                <h4 class="text-xs font-bold text-theme-muted uppercase mb-1">Deskripsi/Catatan</h4>
                                <p class="text-sm text-theme-muted bg-theme-body p-3 rounded-xl border border-theme-border" x-text="selectedData.deskripsi"></p>
                            </div>

                            <!-- Daftar Proker Terhubung (Hanya tampil saat Indikator diklik) -->
                            <template x-if="selectedData.connected_prokers !== undefined && selectedData.connected_prokers.length > 0">
                                <div class="pt-4 mt-2 border-t border-theme-border">
                                    <h4 class="text-xs font-bold text-theme-muted uppercase mb-3">Program Kerja Terhubung (<span x-text="selectedData.connected_prokers.length"></span>)</h4>
                                    
                                    <!-- Scroll khusus list dimatikan, karena seluruh body modal sudah bisa di scroll -->
                                    <div class="space-y-2 pb-4">
                                        <template x-for="proker in selectedData.connected_prokers" :key="proker.id">
                                            <div class="p-3 bg-theme-body rounded-xl border border-theme-border text-left hover:border-primary/50 transition-colors">
                                                <p class="text-[10px] font-bold text-primary uppercase mb-0.5" x-text="proker.unit"></p>
                                                <p class="text-sm font-bold text-theme-text" x-text="proker.nama_proker"></p>
                                                <div class="flex justify-between items-center mt-3">
                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-theme-surface border border-theme-border text-theme-muted" x-text="proker.status"></span>
                                                    <span class="text-xs font-extrabold text-emerald-600" x-text="formatRupiah(proker.anggaran_rencana)"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- SCRIPT LOGIKA VIS.JS & ALPINE -->
    <script>
        // Ambil Data awal dari PHP via json
        const initialNodes = @json($graphData['nodes']);
        const initialEdges = @json($graphData['edges']);

        document.addEventListener('alpine:init', () => {
            Alpine.data('networkGraph', () => {
                return {
                    network: null,
                    nodesDataSet: null,
                    edgesDataSet: null,
                    isCalculating: true, // Untuk menampilkan animasi loading
                    showModal: false,
                    selectedData: null,
                    nodeDetailsDictionary: @json($nodeDetails), // Kamus data untuk modal
                    zoomLevel: 1.0, // Skala zoom awal slider

                    init() {
                        this.initGraph(initialNodes, initialEdges);

                        // MENDENGARKAN EVENT DARI LIVEWIRE SAAT FILTER BERUBAH
                        window.addEventListener('update-network-data', (e) => {
                            // Ambil data dari event Livewire (bisa berbentuk array atau object tergantung parsing Laravel)
                            let newNodes = Array.isArray(e.detail[0].nodes) ? e.detail[0].nodes : Object.values(e.detail[0].nodes || {});
                            let newEdges = Array.isArray(e.detail[0].edges) ? e.detail[0].edges : Object.values(e.detail[0].edges || {});
                            let newDetails = e.detail[0].details || {};
                            
                            this.updateData(newNodes, newEdges, newDetails);
                        });
                    },

                    initGraph(nodesArray, edgesArray) {
                        const container = document.getElementById('network-container');

                        // Pastikan formatnya adalah Array untuk disuntikkan ke Vis.DataSet
                        let finalNodes = Array.isArray(nodesArray) ? nodesArray : Object.values(nodesArray || {});
                        let finalEdges = Array.isArray(edgesArray) ? edgesArray : Object.values(edgesArray || {});

                        this.nodesDataSet = new vis.DataSet(finalNodes);
                        this.edgesDataSet = new vis.DataSet(finalEdges);

                        const data = {
                            nodes: this.nodesDataSet,
                            edges: this.edgesDataSet
                        };

                        const options = {
                            physics: {
                                forceAtlas2Based: {
                                    gravitationalConstant: -100,
                                    centralGravity: 0.01,
                                    springLength: 200,
                                    springConstant: 0.08
                                },
                                maxVelocity: 50,
                                solver: 'forceAtlas2Based',
                                timestep: 0.35,
                                stabilization: { iterations: 150 }
                            },
                            interaction: {
                                hover: true,
                                tooltipDelay: 200,
                                zoomView: true,
                                dragView: true
                            }
                        };

                        // Render Network
                        this.network = new vis.Network(container, data, options);

                        // EVENT: SETELAH MENGGAMBAR PERTAMA KALI
                        this.network.once("afterDrawing", () => {
                            this.zoomLevel = this.network.getScale();
                        });

                        // EVENT: KETIKA ZOOM VIA MOUSE SCROLL
                        this.network.on("zoom", (params) => {
                            this.zoomLevel = params.scale;
                        });

                        // EVENT: MATIKAN LOADING SAAT GRAFIK STABIL & KUNCI POSISI
                        this.network.once("stabilizationIterationsDone", () => {
                            this.isCalculating = false;
                            // Mematikan efek pegas (physics) agar saat digeser, kotaknya diam (tetap)
                            this.network.setOptions({ physics: false }); 
                        });

                        // EVENT: KETIKA NODE DIKLIK (BUKA MODAL)
                        this.network.on("click", (params) => {
                            if (params.nodes.length > 0) {
                                const nodeId = params.nodes[0];
                                this.selectedData = this.nodeDetailsDictionary[nodeId];
                                this.showModal = true;
                            } else {
                                this.showModal = false;
                            }
                        });

                        // MENGGANTI BEHAVIOR SCROLL / TOUCHPAD MENGGUNAKAN GAYA FIGMA
                        container.addEventListener('wheel', (e) => {
                            // Pinch-to-zoom (cubit trackpad) atau Ctrl+Scroll akan menghasilkan e.ctrlKey = true
                            // Jika true, biarkan vis-network yang mengurus zoom secara otomatis agar titik fokusnya pas.
                            if (!e.ctrlKey) {
                                // Gestur geser 2 jari di trackpad (tanpa Ctrl) -> Geser Kanvas (Pan)
                                e.preventDefault();
                                e.stopPropagation(); // Cegah vis-network membaca ini sebagai instruksi zoom

                                const position = this.network.getViewPosition();
                                const scale = this.network.getScale();

                                // Pindahkan kanvas sesuai arah pergeseran jari (seperti di Figma)
                                this.network.moveTo({
                                    position: {
                                        x: position.x + (e.deltaX / scale),
                                        y: position.y + (e.deltaY / scale)
                                    },
                                    animation: false // Matikan animasi agar pergeseran jari terasa instan & real-time
                                });
                            }
                        }, { passive: false, capture: true });
                    },

                    // Fungsi yang dipanggil saat filter dropdown bekerja
                    updateData(newNodes, newEdges, newDetails) {
                        this.isCalculating = true; // Nyalakan loading
                        
                        // Failsafe: Matikan loading paksa setelah 2 detik (jika data kosong / proses terlalu cepat)
                        setTimeout(() => { 
                            this.isCalculating = false; 
                            if(this.network) this.network.setOptions({ physics: false });
                        }, 2000);

                        // Timpa dictionary baru
                        this.nodeDetailsDictionary = newDetails;

                        // Perbarui data di dalam grafik
                        this.nodesDataSet.clear();
                        this.edgesDataSet.clear();
                        
                        this.nodesDataSet.add(newNodes);
                        this.edgesDataSet.add(newEdges);

                        // Minta jaringan menyalakan physics sementara untuk menata ulang posisi
                        this.network.setOptions({ physics: true });
                        this.network.stabilize();
                        
                        // Matikan loading otomatis & kunci posisi kembali jika stabilisasi selesai
                        this.network.once("stabilizationIterationsDone", () => {
                            this.isCalculating = false;
                            this.network.setOptions({ physics: false }); // Kunci posisi agar diam
                        });
                    },

                    // PENGATURAN SLIDER ZOOM
                    updateZoom() {
                        if (this.network) {
                            this.network.moveTo({
                                scale: parseFloat(this.zoomLevel),
                                animation: {
                                    duration: 50, // sangat singkat agar slider responsif
                                    easingFunction: "linear"
                                }
                            });
                        }
                    },
                    zoomIn() {
                        let newScale = parseFloat(this.zoomLevel) + 0.2;
                        if (newScale > 2.5) newScale = 2.5;
                        this.zoomLevel = newScale;
                        this.updateZoom();
                    },
                    zoomOut() {
                        let newScale = parseFloat(this.zoomLevel) - 0.2;
                        if (newScale < 0.1) newScale = 0.1;
                        this.zoomLevel = newScale;
                        this.updateZoom();
                    },

                    // Helper Formatter Rupiah untuk Modal
                    formatRupiah(angka) {
                        if(!angka) return 'Rp 0';
                        return new Intl.NumberFormat('id-ID', {
                            style: 'currency',
                            currency: 'IDR',
                            minimumFractionDigits: 0
                        }).format(angka);
                    }
                }
            });
        });
    </script>
</div>