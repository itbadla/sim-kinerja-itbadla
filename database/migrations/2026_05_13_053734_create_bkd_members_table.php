<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bkd_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bkd_activity_id')->constrained('bkd_activities')->cascadeOnDelete();
            
            // Jika anggota adalah dosen internal ITBADLA
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Jika anggota adalah orang luar/mahasiswa (tidak punya akun)
            $table->string('nama_anggota')->nullable(); 
            
            // Peran dalam kegiatan (Sesuai PO BKD)
            $table->enum('peran', ['ketua', 'anggota', 'penulis_utama', 'penulis_korespondensi']);
            $table->boolean('is_aktif')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkd_members');
    }
};