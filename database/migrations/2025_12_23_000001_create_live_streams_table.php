<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('stream_key')->unique();
            $table->string('rtmp_url')->nullable();
            $table->string('hls_url')->nullable();
            $table->enum('status', ['scheduled', 'live', 'ended'])->default('scheduled');
            $table->integer('viewer_count')->default(0);
            $table->integer('max_viewers')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_private')->default(false);
            $table->string('category')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index('user_id');
        });

        Schema::create('stream_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['live_stream_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_viewers');
        Schema::dropIfExists('live_streams');
    }
};