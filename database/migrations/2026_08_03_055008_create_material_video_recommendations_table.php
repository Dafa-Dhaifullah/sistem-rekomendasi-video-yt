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
    Schema::create('material_video_recommendations', function (Blueprint $table) {
        $table->id();
        
        // Beri batasan panjang karakter di argumen kedua agar ukuran index tidak kelebihan beban
        $table->string('tahun', 10);              // Cukup 10 karakter (misal: "20252")
        $table->string('kode_prodi', 10);         // Cukup 10 karakter (misal: "57201")
        $table->string('kode_mata_kuliah', 20);   // Cukup 20 karakter (misal: "ISUWP4327")
        $table->string('kelas', 10);              // Cukup 10 karakter (misal: "A")
        $table->integer('pertemuan');
        
        $table->string('video_id_1')->nullable();
        $table->string('video_id_2')->nullable();
        $table->string('video_id_3')->nullable();
        $table->string('video_id_4')->nullable();
        $table->string('video_id_5')->nullable();
        
        $table->json('skor_kemiripan')->nullable();
        $table->timestamps();

        // Karena panjang string sudah dibatasi, pembuatan index ini sekarang aman
        $table->index(['tahun', 'kode_prodi', 'kode_mata_kuliah', 'kelas', 'pertemuan'], 'idx_rekomendasi_pertemuan');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_video_recommendations');
    }
};
