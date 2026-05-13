PROJECT BRIEF: SIM KINERJA & BKD ITBADLA
Institut Teknologi dan Bisnis Ahmad Dahlan Lamongan

1. Pendahuluan & Tujuan
SIM Kinerja ITBADLA adalah platform digital terintegrasi yang dirancang untuk mengelola, memonitor, dan mengevaluasi kinerja seluruh civitas akademika (Dosen & Tendik) di lingkungan Institut Teknologi dan Bisnis Ahmad Dahlan Lamongan.

Sistem ini bertujuan untuk memastikan setiap aktivitas harian dan program kerja selaras dengan Indikator Kinerja Utama (IKU) dan Indikator Kinerja Tambahan (IKT) yang ditetapkan institusi, sekaligus memfasilitasi pelaporan wajib Beban Kerja Dosen (BKD) / Tridharma yang terintegrasi langsung dengan platform SISTER Kemdikbudristek.

2. Arsitektur Otoritas & Rantai Komando
Sistem menggunakan pendekatan Hierarki Berbasis Level Otoritas Jabatan. Rantai komando (siapa memverifikasi siapa) ditentukan secara otomatis oleh sistem berdasarkan posisi jabatan dan unit tempat user bernaung.

Level 1 (Puncak): Rektor.

Level 2 (Pimpinan Institut): Wakil Rektor.

Level 3 (Pimpinan Menengah): Dekan Fakultas, Ketua Lembaga (LPM/LPPM), Kepala Biro.

Level 4 (Pimpinan Program Studi): Kepala Program Studi (Kaprodi).

Level 5 (Akademik): Dosen.

Level 6 (Operasional): Tenaga Kependidikan (Tendik) / Staff.

Solusi Kasus Khusus:

Rangkap Jabatan: 1 User dapat memiliki banyak peran (misal: Role Dosen + Role Kaprodi). Verifikasi akan diarahkan secara dinamis sesuai dengan "Konteks Unit" (dropdown) yang dipilih user saat melaporkan kinerjanya.

Dosen Lintas Prodi: Sistem mendukung penempatan satu dosen di beberapa prodi sekaligus melalui tabel pivot penempatan (unit_user), dengan tetap menjaga rantai verifikasi ke atasan yang tepat di masing-masing unit.

3. Integrasi Autentikasi & Domain Email
Sistem menggunakan Google SSO (Single Sign-On) untuk keamanan dan kemudahan akses. Sistem secara otomatis melakukan klasifikasi akun berdasarkan domain email:

Domain @ahmaddahlan.ac.id: Dikhususkan untuk Dosen dan Pimpinan. Sistem otomatis memberikan role dasar "Dosen" saat pertama kali login.

Domain @staff.ahmaddahlan.ac.id: Dikhususkan untuk Tenaga Kependidikan / Staff. Sistem otomatis memberikan role "Staff" saat pertama kali login.

Keamanan: Email di luar domain resmi institusi (seperti @gmail.com) akan otomatis ditolak oleh sistem.

4. Struktur Modul Aplikasi
MODUL I: Master Data & Administrator (Pondasi)

Kelola Pengguna: Manajemen akun, sinkronisasi Google ID, dan fitur Impersonate (Admin login sebagai user lain untuk audit).

Master Jabatan: Pengaturan nama jabatan (Rektor s.d Staff) dan bobot Level Otoritas (1-6).

Struktur Unit: Pemetaan hierarki Institut → Fakultas/Lembaga → Program Studi/Bagian.

Master IKU/IKT: Input daftar indikator target institusi sebagai acuan kerja periode berjalan.

MODUL II: Aktivitas Kinerja (Logbook)

Logbook Harian: Input aktivitas harian, output, dan unggah bukti dukung yang ditautkan ke Proker/IKU serta Unit Kerja spesifik.

Verifikasi Berjenjang: Fitur bagi atasan untuk menyetujui, memberi catatan revisi, atau menolak logbook bawahan sesuai rantai komando.

Team Saya (My Team): Dashboard pantauan cepat bagi pimpinan untuk melihat kinerja anggota timnya secara real-time.

MODUL III: Perencanaan (Proker)

Penyusunan Proker: Unit kerja menyusun rencana kegiatan tahunan dan target pencapaian IKU.

Plotting Anggaran: Estimasi biaya per kegiatan yang nantinya akan menjadi rujukan mutlak di modul keuangan.

MODUL IV: Keuangan & LPJ (Finance)

Pengajuan Dana: Staff/Panitia mengajukan pencairan dana (Pribadi/Lembaga) yang dapat ditautkan langsung ke Program Kerja (Proker) yang sudah disetujui.

Laporan Pertanggungjawaban (LPJ): Unggah bukti kuitansi dan laporan realisasi setelah kegiatan selesai.

Verifikasi Keuangan: Persetujuan pencairan dana oleh Biro Keuangan atau Wakil Rektor II beserta pencatatan status pencairan.

MODUL V: Kinerja Tridharma & BKD (Integrasi SISTER) [BARU]

Pencatatan Tridharma: Form pelaporan terstruktur yang dibagi dalam 4 kategori baku: Pendidikan, Penelitian, Pengabdian kepada Masyarakat, dan Penunjang.

Manajemen Anggota & Dokumen: Mendukung input kegiatan berkelompok (Ketua & Anggota) serta pengunggahan dokumen bukti yang telah dipetakan dengan rubrik BKD.

Two-Way API Synchronization (SISTER):

PULL: Menarik riwayat data dosen dari server SISTER ke SIM lokal kampus.

PUSH: Mendorong (sinkronisasi) data penelitian/pengabdian baru yang diinput di SIM lokal langsung ke database SISTER Kemdikbudristek (menggunakan sister_id untuk mencegah duplikasi).

Verifikasi Internal BKD: Proses review dokumen oleh Asesor Internal Kampus sebelum data resmi disinkronisasi/dikirim ke SISTER.

5. Spesifikasi Teknologi (Tech Stack)
Framework Utama: Laravel 13.

Bahasa Pemrograman: PHP 8.3.

Engine UI: Livewire v3 dengan format Volt Component (Modern, SPA-like, & Reactive).

Styling: Tailwind CSS dengan kustomisasi tema identitas ITBADLA.

Database: MySQL dengan optimasi arsitektur relasi pivot (unit_user) untuk fleksibilitas jabatan dan performa tinggi.

Library Ekosistem:

Spatie Laravel Permission: Untuk Role-Based Access Control (RBAC).

Laravel Socialite: Untuk autentikasi Google SSO.

Laravel HTTP Client (Guzzle): Untuk integrasi RESTful API dengan layanan SISTER.