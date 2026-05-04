<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache untuk menghindari error sinkronisasi
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- STEP 1: DEFINISI PERMISSIONS ---
        $permissionsByGroup = [
            'utama' => ['akses-dashboard', 'akses-profil'],
            'kinerja' => ['isi-logbook', 'akses-tridharma', 'verifikasi-logbook'],
            'keuangan' => ['ajukan-dana', 'track-dana', 'verifikasi-dana', 'kelola-lpj'],
            'lembaga' => ['monitoring-unit', 'akses-lppm', 'akses-lpm'],
            'sistem' => ['akses-dokumen', 'kelola-user', 'kelola-unit', 'kelola-role', 'kelola-master'],
        ];

        foreach ($permissionsByGroup as $group => $names) {
            foreach ($names as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }
        }

        // --- STEP 2: DEFINISI ROLES ---
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->syncPermissions(Permission::all());

        $roleDosen = Role::firstOrCreate(['name' => 'dosen'])->syncPermissions([
            'akses-dashboard', 'akses-profil', 'isi-logbook', 'akses-tridharma', 'ajukan-dana', 'track-dana', 'kelola-lpj', 'akses-dokumen'
        ]);

        $roleLembaga = Role::firstOrCreate(['name' => 'lembaga'])->syncPermissions([
            'akses-dashboard', 'isi-logbook', 'verifikasi-logbook', 'monitoring-unit', 'ajukan-dana', 'track-dana', 'kelola-lpj'
        ]);

        $roleKeuangan = Role::firstOrCreate(['name' => 'keuangan'])->syncPermissions([
            'akses-dashboard', 'verifikasi-dana', 'track-dana', 'akses-dokumen'
        ]);

        $roleMagang = Role::firstOrCreate(['name' => 'magang'])->syncPermissions([
            'akses-dashboard', 'akses-profil', 'isi-logbook'
        ]);

        // --- STEP 3: SEEDING DEPARTEMEN (UNITS) ---
        
        // 1. Unit Non-Akademik / Biro
        $biroUnits = [
            ['kode' => 'LPM', 'nama' => 'Lembaga Penjaminan Mutu'],
            ['kode' => 'LUIK', 'nama' => 'Lembaga Urusan Internasional & Kerjasama'],
            ['kode' => 'LAIK', 'nama' => 'Lembaga Al-Islam & Kemuhammadiyahan'],
            ['kode' => 'LPPM', 'nama' => 'Lembaga Penelitian & Pengabdian Masyarakat'],
            ['kode' => 'BAUK', 'nama' => 'Biro Administrasi Umum & Keuangan'],
            ['kode' => 'BAAK', 'nama' => 'Biro Administrasi Akademik & Kemahasiswaan'],
            ['kode' => 'SDI', 'nama' => 'Sumber Daya Insani'],
            ['kode' => 'JARKOMIT', 'nama' => 'Jaringan Komunikasi & IT'],
            ['kode' => 'LKPMB', 'nama' => 'Lembaga Kerjasama & Pemasaran Mahasiswa Baru'],
            ['kode' => 'LABKOM', 'nama' => 'Laboratorium Komputer'],
            ['kode' => 'PERPUS', 'nama' => 'Perpustakaan'],
            ['kode' => 'INKBIS', 'nama' => 'Inkubator Bisnis'],
            ['kode' => 'LAZISMU', 'nama' => 'Lazismu Kantor Layanan ITB AD'],
            ['kode' => 'CC', 'nama' => 'Career Center'],
        ];

        foreach ($biroUnits as $u) {
            Unit::updateOrCreate(['kode_unit' => $u['kode']], ['nama_unit' => $u['nama']]);
        }

        // 2. Unit Akademik (Fakultas & Prodi)
        // Fakultas Teknik
        $ft = Unit::updateOrCreate(['kode_unit' => 'FT'], ['nama_unit' => 'Fakultas Teknik (Dekan)']);
        $prodiFT = [
            ['kode' => 'TI', 'nama' => 'Teknik Informatika'],
            ['kode' => 'TS', 'nama' => 'Teknik Sipil'],
            ['kode' => 'ARS', 'nama' => 'Arsitektur'],
        ];
        foreach ($prodiFT as $p) {
            Unit::updateOrCreate(
                ['kode_unit' => $p['kode']], 
                ['nama_unit' => 'Prodi ' . $p['nama'], 'parent_id' => $ft->id]
            );
        }

        // Fakultas Ekonomi & Bisnis
        $feb = Unit::updateOrCreate(['kode_unit' => 'FEB'], ['nama_unit' => 'Fakultas Ekonomi & Bisnis (Dekan)']);
        $prodiFEB = [
            ['kode' => 'MJ', 'nama' => 'Manajemen'],
            ['kode' => 'BD', 'nama' => 'Bisnis Digital'],
            ['kode' => 'AK', 'nama' => 'Akuntansi'],
            ['kode' => 'PJK', 'nama' => 'Perpajakan'],
        ];
        foreach ($prodiFEB as $p) {
            Unit::updateOrCreate(
                ['kode_unit' => $p['kode']], 
                ['nama_unit' => 'Prodi ' . $p['nama'], 'parent_id' => $feb->id]
            );
        }

        // --- STEP 4: BUAT DEFAULT USER & PLOTTING ---

        // 1. Super Admin (Jarkomit)
        $uJarkomit = Unit::where('kode_unit', 'JARKOMIT')->first();
        $adminIT = User::updateOrCreate(
            ['email' => 'jarkomit@ahmaddahlan.ac.id'],
            [
                'name' => 'Administrator ITBADLA',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'unit_id' => $uJarkomit->id,
                'jabatan' => 'Kepala Jarkomit'
            ]
        );
        $adminIT->assignRole($roleAdmin);
        $uJarkomit->update(['kepala_unit_id' => $adminIT->id]);

        // 2. Skenario Pak Ahmad (Dosen, Kaprodi TI, & Ka. LPPM)
        $uTI = Unit::where('kode_unit', 'TI')->first();
        $uLPPM = Unit::where('kode_unit', 'LPPM')->first();

        $pakAhmad = User::updateOrCreate(
            ['email' => 'ahmad@ahmaddahlan.ac.id'],
            [
                'name' => 'Ahmad, M.Kom',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'unit_id' => $uTI->id, // Homebase di Prodi TI
                'jabatan' => 'Dosen / Kaprodi TI / Ka. LPPM'
            ]
        );
        // Berikan Role Dosen (untuk isi logbook) dan Lembaga (untuk verifikasi)
        $pakAhmad->assignRole([$roleDosen, $roleLembaga]);
        
        // Plotting sebagai Kepala di dua unit
        $uTI->update(['kepala_unit_id' => $pakAhmad->id]);
        $uLPPM->update(['kepala_unit_id' => $pakAhmad->id]);

        // 3. Contoh Dosen Biasa (Prodi Akuntansi)
        $uAK = Unit::where('kode_unit', 'AK')->first();
        $dosenAK = User::updateOrCreate(
            ['email' => 'dosen.ak@ahmaddahlan.ac.id'],
            [
                'name' => 'Dosen Akuntansi, S.E., M.Ak.',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'unit_id' => $uAK->id,
                'jabatan' => 'Dosen Tetap'
            ]
        );
        $dosenAK->assignRole($roleDosen);

        // 4. Contoh Anak Magang (di Jarkomit)
        $magang = User::updateOrCreate(
            ['email' => 'magang@itbad.ac.id'],
            [
                'name' => 'Siswa Magang Jarkomit',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'unit_id' => $uJarkomit->id,
                'jabatan' => 'Junior Web Developer'
            ]
        );
        $magang->assignRole($roleMagang);
    }
}