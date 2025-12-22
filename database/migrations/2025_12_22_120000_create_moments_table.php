<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('privacy', ['public', 'private'])->default('public');
            $table->boolean('is_featured')->default(false);
            $table->integer('posts_count')->default(0);
            $table->integer('views_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'privacy']);
            $table->index('is_featured');
        });

        Schema::create('moment_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moment_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['moment_id', 'post_id']);
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moment_posts');
        Schema::dropIfExists('moments');
    }
};
