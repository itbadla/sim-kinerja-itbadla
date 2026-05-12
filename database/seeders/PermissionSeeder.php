<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reset cache role dan permission (Sangat penting agar error tidak muncul)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. DAFTAR PERMISSION (HAK AKSES)
        $permissions = [
            // Khusus pimpinan
            'monitoring-universitas',
            
            // Utama
            'dasbor', 'profil-saya',
            
            // Perencanaan
            'program-kerja', 'verifikasi-raker',
            
            // Kinerja
            'logbook-harian', 'verifikasi-logbook', 'team-saya',
            
            // Keuangan
            'pengajuan-dana', 'verifikasi-keuangan', 'laporan-lpj',
            
            // Administrator
            'kelola-user', 'kelola-unit', 'peran-dan-izin', 'master-jabatan', 'indikator-kinerja'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 3. DEFINISI PERAN (ROLES) & DISTRIBUSI HAK AKSES
        // Kita petakan peran sesuai dengan Master Jabatan nanti
        $rolePermissions = [
            'Rektor'          => ['dasbor', 'profil-saya', 'verifikasi-raker', 'verifikasi-logbook', 'team-saya', 'monitoring-universitas'],
            'Wakil Rektor'    => ['dasbor', 'profil-saya', 'verifikasi-raker', 'verifikasi-logbook', 'team-saya', 'monitoring-universitas'],
            'Dekan'           => ['dasbor', 'profil-saya', 'program-kerja', 'logbook-harian', 'verifikasi-logbook', 'pengajuan-dana', 'team-saya'],
            'Kepala Lembaga'  => ['dasbor', 'profil-saya', 'program-kerja', 'logbook-harian', 'verifikasi-logbook', 'pengajuan-dana', 'team-saya'],
            'Kepala Biro'     => ['dasbor', 'profil-saya', 'program-kerja', 'logbook-harian', 'verifikasi-logbook', 'pengajuan-dana', 'team-saya'],
            'Kepala Unit'     => ['dasbor', 'profil-saya', 'program-kerja', 'logbook-harian', 'verifikasi-logbook', 'pengajuan-dana', 'team-saya'],
            'Kaprodi'         => ['dasbor', 'profil-saya', 'program-kerja', 'logbook-harian', 'verifikasi-logbook', 'pengajuan-dana', 'team-saya'],
            'Dosen'           => ['dasbor', 'profil-saya', 'logbook-harian', 'pengajuan-dana'],
            'Staff'           => ['dasbor', 'profil-saya', 'logbook-harian', 'pengajuan-dana'],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // 4. SUPER ADMIN (Mendapatkan semua akses)
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());
    }
}