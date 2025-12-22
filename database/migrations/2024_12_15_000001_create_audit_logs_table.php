<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 100)->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('timestamp')->index();
            $table->string('session_id', 100)->nullable()->index();
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low')->index();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Composite indexes for performance
            $table->index(['user_id', 'timestamp']);
            $table->index(['action', 'timestamp']);
            $table->index(['risk_level', 'timestamp']);
            $table->index(['ip_address', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
