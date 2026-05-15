📑 BRIEF SISTEM: MODUL KEUANGAN END-TO-END

Tujuan Utama Sistem:

Menciptakan ekosistem tata kelola dana operasional dan kegiatan kampus yang transparan, akuntabel, fleksibel (mendukung skema termin), dan memiliki rekam jejak audit (audit trail) yang ketat sesuai standar akuntansi institusi.

TAHAP 1: Pengajuan Dana (Oleh Dosen / Unit)

Fase di mana pengguna meminta alokasi anggaran kepada institusi.



Kepemilikan Cerdas (Smart Ownership): Sistem membedakan pengajuan "Pribadi" (milik dosen terkait) dan "Lembaga" (milik Unit/Prodi/Fakultas). Jika Kepala Prodi diganti, Kepala Prodi yang baru akan secara otomatis mewarisi akses untuk melihat dan melaporkan LPJ dari kepengurusan sebelumnya dan pengurus sebelumnya di pastikan tidak mendapatkan informasi apapun terkait dengan lembaga yangdia pimpim sebelumnya.

Integrasi Proker: Pengajuan dapat ditautkan ke Program Kerja (Proker) yang sudah disahkan sebelumnya.

Usulan Skema Pencairan: Pengaju dapat me-request apakah dana ingin dicairkan sekaligus (Lumpsum) atau bertahap (Termin), lengkap dengan usulan jumlah terminnya.

TAHAP 2: Verifikasi Proposal (Oleh Pimpinan / Keuangan)

Fase persetujuan administratif proposal.



Penyesuaian Anggaran (Budget Cut): Verifikator memiliki hak untuk memotong atau menyesuaikan nominal yang diajukan menjadi "Nominal Disetujui".

Kendali Skema Pencairan: Verifikator berhak menentukan secara mutlak skema pencairan. Jika disetujui secara Termin (maksimal 24 kali), sistem akan menyediakan kalkulator otomatis untuk membagi rata nominal per termin.

Validasi Matematis: Sistem menolak penyimpanan jika total pembagian dana termin tidak sama persis dengan total dana yang disetujui.



TAHAP 3: Pencairan & Transfer Dana (Oleh Bagian Keuangan)

Fase eksekusi pengiriman uang nyata ke rekening pengaju.



Pemisahan Otoritas: Hanya pengguna dengan hak akses / Role keuangan yang dapat melihat meja kerja ini dan melakukan pencairan. Pimpinan unit biasa tidak bisa mentransfer dana.

Upload Bukti Transfer: Keuangan wajib mengunggah bukti transfer dari bank/kampus sebagai tanda sah bahwa uang telah dikirim.

Kuncian Berantai (Chain Lock): Ini adalah fitur keamanan tertinggi. Sistem akan mengunci pencairan dana Termin ke-2, Termin ke-3, dst., selama Dosen belum menyelesaikan LPJ pada Termin sebelumnya.



TAHAP 4: Laporan Pertanggungjawaban / LPJ (Oleh Dosen / Unit)

Fase di mana pengaju melaporkan penggunaan dana yang telah mereka terima.



Pelaporan Berbasis Termin: Dosen melaporkan LPJ secara spesifik per termin yang cair, bukan digabung di akhir proyek. Dosen wajib mengunggah scan nota/struk belanja (Maks 5MB).

Auto-Kalkulasi Sisa Dana (SiLPA): Sistem menghitung selisih antara "Dana Cair" vs "Realisasi Terpakai".

Kewajiban Pengembalian (Refund): Jika terdapat sisa dana (Rp 1 pun), sistem akan secara otomatis memunculkan kolom baru yang memaksa Dosen untuk melakukan transfer balik ke kampus dan mengunggah Bukti Transfer Pengembalian Uang. Dosen tidak bisa mengirim LPJ jika bukti ini kosong.



TAHAP 5: Audit & Verifikasi LPJ (Oleh Bagian Keuangan)

Fase pemeriksaan keabsahan nota dan uang kembalian.



Tinjauan Dokumen: Keuangan dapat mengecek dokumen LPJ dan Bukti Transfer Kembalian berdampingan.

Mekanisme Revisi: Jika nota tidak sah (misal tidak ada stempel), Keuangan dapat menolak LPJ dengan memberikan "Catatan Revisi". Status akan berubah menjadi "Revisi" di dasbor Dosen.

Buka Kunci Otomatis: Saat Keuangan mengeklik "Sesuai & Selesai" pada LPJ Termin 1, maka antrean pencairan untuk Termin 2 di "Tahap 3" akan otomatis terbuka kuncinya agar kasir bisa mentransfer dana berikutnya.

KEUNGGULAN ARSITEKTUR DATABASE (Di Balik Layar)

Relasi Master-Child (fund_submissions & fund_disbursements): Mengizinkan 1 pengajuan memiliki riwayat pencairan, pelaporan, dan pengembalian yang tidak terbatas (bebas masalah untuk pendanaan riset berjangka panjang).

Audit Trail (Jejak Aktor): Database secara diam-diam mencatat 3 ID User penting di setiap transaksi:

verified_by: Siapa yang menyetujui proposal awal.

cair_processed_by: Siapa kasir/staf yang mentransfer uang ke dosen.

lpj_verified_by: Siapa auditor yang memvalidasi kebenaran nota LPJ.