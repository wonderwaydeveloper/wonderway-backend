<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_note_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('vote_type', ['helpful', 'not_helpful']);
            $table->timestamps();

            $table->unique(['community_note_id', 'user_id']);
            $table->index('vote_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_note_votes');
    }
};