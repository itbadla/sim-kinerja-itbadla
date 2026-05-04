<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_submissions', function (Blueprint $table) {
            // Nominal asli yang dibelanjakan
            $table->decimal('nominal_realisasi', 15, 2)->nullable()->after('nominal');
            // File bukti struk/kuitansi
            $table->string('file_lpj')->nullable()->after('file_lampiran');
            // Status khusus untuk LPJ
            $table->enum('status_lpj', ['belum', 'menunggu_verifikasi', 'selesai'])->default('belum')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('fund_submissions', function (Blueprint $table) {
            $table->dropColumn(['nominal_realisasi', 'file_lpj', 'status_lpj']);
        });
    }
};