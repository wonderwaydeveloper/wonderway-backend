<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->string('media_url')->nullable();
            $table->json('target_audience')->nullable();
            $table->decimal('budget', 10, 2);
            $table->decimal('cost_per_click', 8, 4)->default(0.10);
            $table->decimal('cost_per_impression', 8, 4)->default(0.01);
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->enum('status', ['pending', 'active', 'paused', 'completed', 'rejected'])->default('pending');
            $table->bigInteger('impressions_count')->default(0);
            $table->bigInteger('clicks_count')->default(0);
            $table->bigInteger('conversions_count')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->json('targeting_criteria')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
            $table->index('advertiser_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};