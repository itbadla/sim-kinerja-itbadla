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
            $table->string('kode_indikator', 20)->unique(); // Contoh: IKU-1, IKT-01
            $table->text('nama_indikator');
            $table->enum('kategori', ['IKU', 'IKT']); // Penyatuan tabel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_indicators');
    }
};
