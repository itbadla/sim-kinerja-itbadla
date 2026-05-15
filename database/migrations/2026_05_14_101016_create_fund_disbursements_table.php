<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fund_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_submission_id')->constrained('fund_submissions')->cascadeOnDelete();
            
            // 1. Identitas Pencairan (Termin)
            $table->integer('termin_ke')->default(1); 
            $table->decimal('nominal_cair', 15, 2); 
            $table->enum('status_cair', ['pending', 'diproses', 'cair'])->default('pending');
            $table->date('tanggal_cair')->nullable();
            $table->string('bukti_transfer_kampus')->nullable(); 
            // PELACAKAN KASIR / PENTRANSFER DANA
            $table->foreignId('cair_processed_by')->nullable()->constrained('users')->nullOnDelete();
            
            // 2. Pelaporan LPJ untuk Termin ini
            $table->enum('status_lpj', ['belum', 'menunggu_verifikasi', 'selesai'])->default('belum');
            $table->decimal('nominal_realisasi', 15, 2)->nullable(); 
            $table->string('file_lpj')->nullable();
            $table->text('catatan_revisi_lpj')->nullable(); 
            // PELACAKAN VERIFIKATOR LPJ
            $table->foreignId('lpj_verified_by')->nullable()->constrained('users')->nullOnDelete();
            
            // 3. Pengembalian Sisa Dana (SiLPA) Khusus Termin ini
            $table->decimal('nominal_kembali', 15, 2)->nullable(); 
            $table->string('bukti_pengembalian')->nullable(); 
            $table->timestamp('waktu_pengembalian')->nullable();
            $table->enum('status_pengembalian', ['tidak_ada', 'menunggu_verifikasi', 'lunas'])->default('tidak_ada');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_disbursements');
    }
};
