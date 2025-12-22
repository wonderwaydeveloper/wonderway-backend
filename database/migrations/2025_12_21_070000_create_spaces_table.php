<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['scheduled', 'live', 'ended'])->default('scheduled');
            $table->enum('privacy', ['public', 'followers', 'invited'])->default('public');
            $table->integer('max_participants')->default(10);
            $table->integer('current_participants')->default(0);
            $table->datetime('scheduled_at')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('ended_at')->nullable();
            $table->json('settings')->nullable(); // Recording, chat enabled, etc.
            $table->timestamps();

            $table->index(['status', 'privacy']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
