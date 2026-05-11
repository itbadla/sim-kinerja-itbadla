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
        // 1. Tabel Utama Program Kerja
        Schema::create('work_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            
            $table->string('nama_proker');
            $table->text('deskripsi')->nullable();
            $table->decimal('anggaran_rencana', 15, 2);
            
            // Status approval dari Rapat Kerja
            $table->enum('status', ['draft', 'review_lpm', 'disetujui', 'ditolak'])->default('draft');
            $table->timestamps();
        });

        // 2. Tabel Pivot: 1 Proker mendukung IKU/IKT yang mana saja
        Schema::create('work_program_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_program_id')->constrained('work_programs')->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained('performance_indicators')->cascadeOnDelete();
            
            // Target capaian untuk IKU tersebut
            $table->float('target_angka'); 
            $table->string('satuan_target', 50); // Contoh: '%', 'Dokumen', 'Mitra'
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_program_indicators');
        Schema::dropIfExists('work_programs');
    }
};
