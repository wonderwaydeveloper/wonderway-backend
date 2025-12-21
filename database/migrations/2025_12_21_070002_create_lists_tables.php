<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('privacy', ['public', 'private'])->default('public');
            $table->integer('members_count')->default(0);
            $table->integer('subscribers_count')->default(0);
            $table->string('banner_image')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'privacy']);
        });

        Schema::create('list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['list_id', 'user_id']);
        });

        Schema::create('list_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['list_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_subscribers');
        Schema::dropIfExists('list_members');
        Schema::dropIfExists('lists');
    }
};