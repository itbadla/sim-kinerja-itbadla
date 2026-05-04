<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbooks', function (Blueprint $table) {
            $table->id();
            
            // Relasi Utama
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete(); // Denormalisasi agar query super cepat
            
            // Waktu Pelaksanaan
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            
            // Detail Kinerja
            $table->string('kategori')->default('tugas_utama'); // Contoh: tugas_utama, tambahan, magang
            $table->text('deskripsi_aktivitas');
            $table->string('output')->nullable(); // Hasil kerja
            
            // Bukti Dukung
            $table->string('file_bukti')->nullable(); // Path di storage
            $table->string('link_bukti')->nullable(); // URL Eksternal
            
            // Status dan Verifikasi
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->text('catatan_verifikator')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // === INDEXING UNTUK OPTIMASI PERFORMA ===
            // Karena tabel ini akan sangat besar, kita pasang index pada kolom yang sering dicari (filter)
            $table->index('tanggal');
            $table->index('status');
            $table->index(['unit_id', 'status']); // Composite index untuk filter dashboard Kepala Unit
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbooks');
    }
};