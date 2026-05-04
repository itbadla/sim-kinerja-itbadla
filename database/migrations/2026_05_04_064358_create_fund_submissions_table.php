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
            
            // Relasi ke tabel users (Siapa yang mengajukan)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Relasi ke tabel units (Atas nama unit apa)
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            
            // Tipe Pengajuan (Pribadi atau Lembaga)
            $table->enum('tipe_pengajuan', ['pribadi', 'lembaga'])->default('pribadi');
            
            // Menggunakan tipe decimal 15,2 untuk nominal Rupiah (Cukup hingga triliunan)
            $table->decimal('nominal', 15, 2);
            
            // Penjelasan untuk apa dana tersebut
            $table->text('keperluan');
            
            // Link atau path file proposal/RAB/Kuitansi
            $table->string('file_lampiran')->nullable(); 
            
            // Status alur verifikasi
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            
            // Alasan jika ditolak atau catatan dari verifikator
            $table->text('catatan_verifikator')->nullable();
            
            $table->timestamps();
            
            // Soft Deletes (Penting untuk audit keuangan agar data tidak bisa dihapus permanen oleh user)
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_submissions');
    }
};