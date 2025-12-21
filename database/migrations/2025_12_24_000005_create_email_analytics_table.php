<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('email_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('email_type');
            $table->enum('status', ['sent', 'opened', 'clicked', 'bounced'])->default('sent');
            $table->timestamp('sent_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->string('clicked_link')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'email_type']);
            $table->index(['sent_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_analytics');
    }
};