<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('kode_unit', 20)->unique()->nullable(); // Contoh: LPPM, JARKOMIT
            $table->string('nama_unit'); 
            
            // Hirarki unit (Misal Prodi di bawah Fakultas)
            $table->foreignId('parent_id')->nullable()->constrained('units')->nullOnDelete();
            
            // Siapa Kepala Unit-nya? (Untuk otomatisasi verifikator)
            // Karena tabel users sudah ada bawaan Laravel, kita bisa langsung relasikan
            $table->foreignId('kepala_unit_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes(); // Aman jika unit dihapus, data tidak hilang permanen
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};