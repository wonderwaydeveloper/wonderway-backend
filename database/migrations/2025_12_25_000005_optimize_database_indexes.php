<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optimize posts table indexes
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['user_id', 'is_draft', 'published_at'], 'posts_timeline_idx');
            $table->index(['published_at', 'likes_count'], 'posts_trending_idx');
            $table->index(['thread_id', 'thread_position'], 'posts_thread_idx');
        });

        // Optimize follows table for timeline queries
        Schema::table('follows', function (Blueprint $table) {
            $table->index(['follower_id', 'created_at'], 'follows_timeline_idx');
        });

        // Optimize likes table
        Schema::table('likes', function (Blueprint $table) {
            $table->index(['likeable_type', 'likeable_id', 'created_at'], 'likes_entity_idx');
        });

        // Optimize notifications table
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at', 'created_at'], 'notifications_user_idx');
        });

        // Optimize analytics_events table
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id', 'event_type'], 'analytics_entity_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_timeline_idx');
            $table->dropIndex('posts_trending_idx');
            $table->dropIndex('posts_thread_idx');
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->dropIndex('follows_timeline_idx');
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->dropIndex('likes_entity_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_idx');
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex('analytics_entity_event_idx');
        });
    }
};