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
        Schema::create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            $table->string('kode_indikator', 20); 
            $table->text('nama_indikator');
            $table->enum('kategori', ['IKU', 'IKT']); 
            $table->timestamps();

            // TAMBAHKAN BARIS INI: Agar kode unik per periode, bukan global
            $table->unique(['periode_id', 'kode_indikator']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_indicators');
    }
};
