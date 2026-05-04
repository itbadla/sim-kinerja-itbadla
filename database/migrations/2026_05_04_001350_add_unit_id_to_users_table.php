<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Plotting user ke unit tertentu
            $table->foreignId('unit_id')->nullable()->after('password')->constrained('units')->nullOnDelete();
            
            // Opsional: Untuk membedakan title (Dosen, Staff IT, Mahasiswa Magang)
            $table->string('jabatan')->nullable()->after('unit_id'); 
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'jabatan']);
        });
    }
};