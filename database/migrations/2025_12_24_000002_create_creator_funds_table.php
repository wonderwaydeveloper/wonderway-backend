<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->integer('month');
            $table->integer('year');
            $table->bigInteger('total_views')->default(0);
            $table->bigInteger('total_engagement')->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('earnings', 10, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
            $table->datetime('paid_at')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->unique(['creator_id', 'month', 'year']);
            $table->index(['month', 'year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_funds');
    }
};