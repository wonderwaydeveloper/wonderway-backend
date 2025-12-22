<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('original_path');
            $table->json('processed_paths')->nullable(); // Different resolutions
            $table->string('thumbnail_path')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->string('resolution')->nullable(); // original resolution
            $table->bigInteger('file_size')->nullable(); // in bytes
            $table->enum('encoding_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('encoding_status');
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};