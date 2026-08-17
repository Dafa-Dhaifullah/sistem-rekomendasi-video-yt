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
    Schema::create('youtube_video_caches', function (Blueprint $table) {
        $table->id();
        $table->string('video_id')->unique();
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('thumbnail_url')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_video_caches');
    }
};
