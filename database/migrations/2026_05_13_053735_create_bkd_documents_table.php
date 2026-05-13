<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bkd_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bkd_activity_id')->constrained('bkd_activities')->cascadeOnDelete();
            
            $table->string('nama_dokumen'); // Contoh: "Surat Tugas Mengajar"
            $table->string('jenis_dokumen')->nullable(); // Kategori dokumen versi SISTER
            
            $table->string('file_path')->nullable(); // Upload file ke server lokal
            $table->string('tautan_luar')->nullable(); // Link Jurnal / Google Drive
            
            // ID Dokumen SISTER jika file ini sukses didorong ke server SISTER
            $table->string('sister_doc_id')->nullable()->unique();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkd_documents');
    }
};