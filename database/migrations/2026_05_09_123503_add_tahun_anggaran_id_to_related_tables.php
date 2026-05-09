<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi untuk menambahkan relasi tahun_anggaran_id.
     */
    public function up(): void
    {
        // 1. Update tabel work_programs (Hapus kolom year lama, ganti ke FK)
        Schema::table('work_programs', function (Blueprint $table) {
            if (Schema::hasColumn('work_programs', 'tahun_anggaran')) {
                $table->dropColumn('tahun_anggaran');
            }
            $table->foreignId('tahun_anggaran_id')
                  ->after('unit_id')
                  ->constrained('tahun_anggaran')
                  ->cascadeOnDelete();
        });

        // 2. Update tabel performance_indicators
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->foreignId('tahun_anggaran_id')
                  ->after('id')
                  ->nullable()
                  ->constrained('tahun_anggaran')
                  ->nullOnDelete();
        });

        // 3. Update tabel logbooks
        Schema::table('logbooks', function (Blueprint $table) {
            $table->foreignId('tahun_anggaran_id')
                  ->after('work_program_id')
                  ->nullable()
                  ->constrained('tahun_anggaran')
                  ->nullOnDelete();
        });

        // 4. Update tabel fund_submissions
        Schema::table('fund_submissions', function (Blueprint $table) {
            $table->foreignId('tahun_anggaran_id')
                  ->after('work_program_id')
                  ->nullable()
                  ->constrained('tahun_anggaran')
                  ->nullOnDelete();
        });
    }

    /**
     * Batalkan perubahan (Rollback).
     */
    public function down(): void
    {
        Schema::table('fund_submissions', function (Blueprint $table) {
            $table->dropForeign(['tahun_anggaran_id']);
            $table->dropColumn('tahun_anggaran_id');
        });

        Schema::table('logbooks', function (Blueprint $table) {
            $table->dropForeign(['tahun_anggaran_id']);
            $table->dropColumn('tahun_anggaran_id');
        });

        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->dropForeign(['tahun_anggaran_id']);
            $table->dropColumn('tahun_anggaran_id');
        });

        Schema::table('work_programs', function (Blueprint $table) {
            $table->dropForeign(['tahun_anggaran_id']);
            $table->dropColumn('tahun_anggaran_id');
            $table->year('tahun_anggaran')->after('unit_id');
        });
    }
};