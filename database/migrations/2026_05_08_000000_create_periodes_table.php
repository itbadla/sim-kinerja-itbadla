<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->string('nama_periode'); // Contoh: "TA 2024/2025" atau "2024"
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('status', ['planning', 'active', 'closed'])->default('planning');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodes');
    }
};