<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * File ini harus memiliki timestamp 2026_05_03 agar jalan SEBELUM unit_user (2026_05_04)
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan'); 
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->integer('level_otoritas'); 
            $table->string('kategori'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};