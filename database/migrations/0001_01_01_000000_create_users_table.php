<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('subscription_plan')->default('basic');
            $table->boolean('is_premium')->default(false);
            $table->string('password');
            $table->string('phone')->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->string('cover')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_child')->default(false);
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->text('two_factor_backup_codes')->nullable();
            $table->json('backup_codes')->nullable();
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->string('google_id')->nullable();
            $table->string('github_id')->nullable();
            $table->string('facebook_id')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->rememberToken();
            $table->json('notification_preferences')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_suspended')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->timestamp('suspended_until')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamps();
            $table->index('email');
            $table->index('username');
            $table->index('phone');
            $table->index(['is_online', 'last_seen_at']);
            $table->index(['is_flagged', 'is_suspended', 'is_banned']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
