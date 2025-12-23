<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_store', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_id');
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['aggregate_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_store');
    }
};
