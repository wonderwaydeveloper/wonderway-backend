<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['host', 'co_host', 'speaker', 'listener'])->default('listener');
            $table->enum('status', ['invited', 'joined', 'left', 'removed'])->default('joined');
            $table->boolean('is_muted')->default(false);
            $table->datetime('joined_at')->nullable();
            $table->datetime('left_at')->nullable();
            $table->timestamps();
            
            $table->unique(['space_id', 'user_id']);
            $table->index(['space_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_participants');
    }
};