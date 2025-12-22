<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('image')->nullable();
            $table->string('video')->nullable();
            $table->string('gif_url')->nullable();
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('flagged_at')->nullable();
            $table->boolean('is_thread')->default(false);
            $table->string('reply_settings')->default('everyone');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('reposts_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->foreignId('quoted_post_id')->nullable()->constrained('posts')->onDelete('cascade');
            $table->foreignId('thread_id')->nullable()->constrained('posts')->onDelete('cascade');
            $table->integer('thread_position')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'published_at']);
            $table->index(['created_at', 'published_at']);
            $table->index(['likes_count', 'comments_count']);
            $table->index(['is_flagged', 'is_hidden', 'is_deleted']);
            $table->index('quoted_post_id');
            $table->index(['thread_id', 'thread_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
