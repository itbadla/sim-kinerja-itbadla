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
        Schema::create('fund_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            
            // Link ke Proker (Wajib agar terukur)
            $table->foreignId('work_program_id')->nullable()->constrained('work_programs')->nullOnDelete();
            
            $table->enum('tipe_pengajuan', ['pribadi', 'lembaga'])->default('pribadi');
            $table->decimal('nominal', 15, 2);
            $table->text('keperluan');
            $table->string('file_lampiran')->nullable(); 
            
            // Status Alur Pengajuan
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('catatan_verifikator')->nullable();
            
            // Bagian LPJ
            $table->enum('status_lpj', ['belum', 'menunggu_verifikasi', 'selesai'])->default('belum');
            $table->decimal('nominal_realisasi', 15, 2)->nullable();
            $table->string('file_lpj')->nullable();
            $table->timestamp('waktu_pengembalian')->nullable();
            $table->string('catatan_pengembalian')->nullable();

            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_submissions');
    }
};
