<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache untuk menghindari error sinkronisasi saat pengembangan
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- STEP 1: DEFINISI PERMISSIONS ---
        // Kita kelompokkan agar mudah dibaca
        $permissionsByGroup = [
            'utama' => [
                'akses-dashboard',
                'akses-profil',
            ],
            'kinerja' => [
                'isi-logbook',
                'akses-tridharma',
                'verifikasi-logbook',
            ],
            'keuangan' => [
                'ajukan-dana',
                'track-dana',
                'verifikasi-dana',
                'kelola-lpj',
            ],
            'lembaga' => [
                'monitoring-unit',
                'akses-lppm',
                'akses-lpm',
            ],
            'sistem' => [
                'akses-dokumen',
                'kelola-user',
                'kelola-role',
                'kelola-master',
            ],
        ];

        // Flat array untuk pembuatan permission
        foreach ($permissionsByGroup as $group => $names) {
            foreach ($names as $name) {
                Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web'
                ]);
            }
        }

        // --- STEP 2: DEFINISI ROLES & ASSIGN PERMISSIONS ---

        // 1. Super Admin
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // 2. Dosen
        Role::firstOrCreate(['name' => 'dosen'])->syncPermissions([
            'akses-dashboard',
            'akses-profil',
            'isi-logbook',
            'akses-tridharma',
            'ajukan-dana',
            'track-dana',
            'kelola-lpj',
            'akses-dokumen',
        ]);

        // 3. Lembaga (Kaprodi/Dekan)
        Role::firstOrCreate(['name' => 'lembaga'])->syncPermissions([
            'akses-dashboard',
            'isi-logbook',
            'verifikasi-logbook',
            'monitoring-unit',
            'ajukan-dana',
            'track-dana',
            'kelola-lpj',
        ]);

        // 4. Keuangan (BAU)
        Role::firstOrCreate(['name' => 'keuangan'])->syncPermissions([
            'akses-dashboard',
            'verifikasi-dana',
            'track-dana',
            'akses-dokumen',
        ]);

        // 5. Auditor (LPPM/LPM)
        Role::firstOrCreate(['name' => 'auditor'])->syncPermissions([
            'akses-dashboard',
            'akses-lppm',
            'akses-lpm',
            'monitoring-unit',
        ]);

        // 6. Anak Magang
        Role::firstOrCreate(['name' => 'magang'])->syncPermissions([
            'akses-dashboard',
            'akses-profil',
            'isi-logbook',
        ]);

        // --- STEP 3: BUAT DEFAULT USER ---

        $userAdmin = User::updateOrCreate(
            ['email' => 'jarkomit@ahmaddahlan.ac.id'],
            [
                'name' => 'Administrator ITBADLA',
                'password' => bcrypt('password'), // Segera ganti setelah login
                'email_verified_at' => now(),
            ]
        );

        // Assign role admin ke user utama
        $userAdmin->assignRole($admin);
    }
}