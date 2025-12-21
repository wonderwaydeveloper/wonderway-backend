<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])->default('draft');
            $table->integer('traffic_percentage')->default(50); // 0-100
            $table->json('variants'); // A, B variants config
            $table->json('targeting_rules')->nullable();
            $table->datetime('starts_at')->nullable();
            $table->datetime('ends_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'starts_at']);
        });

        Schema::create('ab_test_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('variant'); // A or B
            $table->timestamp('assigned_at');
            
            $table->unique(['ab_test_id', 'user_id']);
            $table->index('variant');
        });

        Schema::create('ab_test_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('variant');
            $table->string('event_type'); // view, click, conversion, etc.
            $table->json('event_data')->nullable();
            $table->timestamps();
            
            $table->index(['ab_test_id', 'variant', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_test_events');
        Schema::dropIfExists('ab_test_participants');
        Schema::dropIfExists('ab_tests');
    }
};