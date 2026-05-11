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
        Schema::create('logbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            // Tautkan ke Proker jika logbook ini adalah progres pengerjaan proker
            $table->foreignId('work_program_id')->nullable()->constrained('work_programs')->nullOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();

            
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            
            $table->string('kategori')->default('tugas_utama');
            $table->text('deskripsi_aktivitas');
            $table->string('output')->nullable(); 
            $table->string('file_bukti')->nullable(); 
            $table->string('link_bukti')->nullable(); 
            
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->text('catatan_verifikator')->nullable();
            
            // Verifikator harian
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Indexing agar dashboard cepat saat memuat ribuan data logbook
            $table->index('tanggal');
            $table->index('status');
            $table->index(['unit_id', 'status']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbooks');
    }
};
