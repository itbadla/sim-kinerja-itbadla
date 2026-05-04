<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_submissions', function (Blueprint $table) {
            // Mencatat kapan uang sisa dikembalikan (Jika null, berarti belum lunas)
            $table->timestamp('waktu_pengembalian')->nullable()->after('status_lpj');
            // Catatan tambahan (misal: "Kembali Cash", "Transfer BCA")
            $table->string('catatan_pengembalian')->nullable()->after('waktu_pengembalian');
        });
    }

    public function down(): void
    {
        Schema::table('fund_submissions', function (Blueprint $table) {
            $table->dropColumn(['waktu_pengembalian', 'catatan_pengembalian']);
        });
    }
};