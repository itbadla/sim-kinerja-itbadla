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
            
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('work_program_id')->nullable()->constrained('work_programs')->nullOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->cascadeOnDelete();
            
            $table->enum('tipe_pengajuan', ['pribadi', 'lembaga'])->default('pribadi');
            $table->decimal('nominal_total', 15, 2); 
            $table->decimal('nominal_disetujui', 15, 2)->nullable();
            $table->text('keperluan');
            $table->string('file_lampiran')->nullable(); 
            
            $table->enum('status_pengajuan', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('catatan_verifikator')->nullable();
            
            // PELACAKAN VERIFIKATOR PROPOSAL
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            
            $table->enum('skema_pencairan', ['lumpsum', 'termin'])->default('lumpsum');

            $table->timestamps();
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