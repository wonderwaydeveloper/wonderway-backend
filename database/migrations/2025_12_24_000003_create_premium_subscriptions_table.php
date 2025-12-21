<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('plan', ['basic', 'premium', 'enterprise']);
            $table->decimal('price', 8, 2);
            $table->enum('billing_cycle', ['monthly', 'yearly']);
            $table->datetime('starts_at');
            $table->datetime('ends_at');
            $table->enum('status', ['active', 'cancelled', 'expired', 'suspended'])->default('active');
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('features')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['ends_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_subscriptions');
    }
};