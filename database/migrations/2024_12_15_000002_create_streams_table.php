<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('stream_key')->unique();
            $table->enum('status', ['created', 'live', 'ended', 'scheduled'])->default('created');
            $table->boolean('is_private')->default(false);
            $table->string('category')->default('general');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->default(0); // in seconds
            $table->integer('peak_viewers')->default(0);
            $table->string('recording_path')->nullable();
            $table->bigInteger('recording_size')->nullable(); // in bytes
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['category', 'status']);
            $table->index('stream_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streams');
    }
};
