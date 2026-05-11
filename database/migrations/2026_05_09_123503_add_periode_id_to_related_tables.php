<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_programs', function (Blueprint $table) {
            if (Schema::hasColumn('work_programs', 'tahun_anggaran')) {
                $table->dropColumn('tahun_anggaran');
            }
            // Menggunakan periode_id
            $table->foreignId('periode_id')->after('unit_id')->constrained('periodes')->cascadeOnDelete();
        });

        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->foreignId('periode_id')->after('id')->nullable()->constrained('periodes')->nullOnDelete();
        });

        Schema::table('logbooks', function (Blueprint $table) {
            $table->foreignId('periode_id')->after('work_program_id')->nullable()->constrained('periodes')->nullOnDelete();
        });

        Schema::table('fund_submissions', function (Blueprint $table) {
            $table->foreignId('periode_id')->after('work_program_id')->nullable()->constrained('periodes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fund_submissions', function (Blueprint $table) {
            $table->dropForeign(['periode_id']);
            $table->dropColumn('periode_id');
        });
        Schema::table('logbooks', function (Blueprint $table) {
            $table->dropForeign(['periode_id']);
            $table->dropColumn('periode_id');
        });
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->dropForeign(['periode_id']);
            $table->dropColumn('periode_id');
        });
        Schema::table('work_programs', function (Blueprint $table) {
            $table->dropForeign(['periode_id']);
            $table->dropColumn('periode_id');
        });
    }
};