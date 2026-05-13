<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bkd_activities', function (Blueprint $table) {
            $table->id();
            // Relasi ke tabel existing
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            
            // KUNCI INTEGRASI: Menyimpan ID SISTER agar tidak duplikat saat ditarik/dikirim
            $table->string('sister_id')->nullable()->unique();
            
            // Detail Kinerja
            $table->enum('kategori_tridharma', ['pendidikan', 'penelitian', 'pengabdian', 'penunjang']);
            $table->string('rubrik_kegiatan_id')->nullable(); // ID Rubrik BKD dari PO BKD / SISTER
            $table->string('judul_kegiatan');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->text('deskripsi')->nullable();
            $table->float('sks_beban')->default(0); // Bobot SKS
            
            // Status Sinkronisasi SISTER
            $table->enum('sync_status', ['un-synced', 'synced', 'failed'])->default('un-synced');
            $table->timestamp('last_synced_at')->nullable();
            
            // Status Verifikasi Internal (Oleh Asesor BKD Kampus)
            $table->enum('status_internal', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->foreignId('asesor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_asesor')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkd_activities');
    }
};