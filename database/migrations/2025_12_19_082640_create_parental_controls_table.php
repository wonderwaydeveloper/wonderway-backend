<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parental_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('require_follow_approval')->default(true);
            $table->boolean('restrict_dm')->default(true);
            $table->boolean('content_filter')->default(true);
            $table->integer('daily_post_limit')->default(10);
            $table->time('usage_start_time')->nullable();
            $table->time('usage_end_time')->nullable();
            $table->timestamps();

            $table->unique('child_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parental_controls');
    }
};
