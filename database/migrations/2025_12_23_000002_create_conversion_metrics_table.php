<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('conversion_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type'); // signup, login, post_create, follow, subscription, etc.
            $table->json('event_data')->nullable();
            $table->string('conversion_type'); // registration, engagement, monetization
            $table->decimal('conversion_value', 10, 2)->default(0);
            $table->string('source')->nullable(); // organic, social, email, etc.
            $table->string('campaign')->nullable();
            $table->string('session_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['conversion_type', 'created_at']);
            $table->index(['user_id', 'event_type']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_metrics');
    }
};
