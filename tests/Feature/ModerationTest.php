<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_report_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/moderation/report', [
                'reportable_type' => 'post',
                'reportable_id' => $post->id,
                'reason' => 'spam',
                'description' => 'This post contains spam content'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reason' => 'spam'
        ]);
    }

    public function test_user_can_report_comment(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/moderation/report', [
                'reportable_type' => 'comment',
                'reportable_id' => $comment->id,
                'reason' => 'harassment'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_type' => 'comment',
            'reportable_id' => $comment->id,
            'reason' => 'harassment'
        ]);
    }

    public function test_user_can_report_user(): void
    {
        $reporter = User::factory()->create();
        $reportedUser = User::factory()->create();

        $response = $this->actingAs($reporter, 'sanctum')
            ->postJson('/api/moderation/report', [
                'reportable_type' => 'user',
                'reportable_id' => $reportedUser->id,
                'reason' => 'harassment'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reportable_type' => 'user',
            'reportable_id' => $reportedUser->id,
            'reason' => 'harassment'
        ]);
    }

    public function test_user_cannot_report_same_content_twice(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        // First report
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/moderation/report', [
                'reportable_type' => 'post',
                'reportable_id' => $post->id,
                'reason' => 'spam'
            ]);

        // Second report (should fail)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/moderation/report', [
                'reportable_type' => 'post',
                'reportable_id' => $post->id,
                'reason' => 'inappropriate'
            ]);

        $response->assertStatus(400);
    }

    public function test_report_requires_valid_reason(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/moderation/report', [
                'reportable_type' => 'post',
                'reportable_id' => $post->id,
                'reason' => 'invalid_reason'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_admin_can_view_reports(): void
    {
        $admin = User::factory()->create();
        
        // Create admin role if not exists
        if (!\Spatie\Permission\Models\Role::where('name', 'admin')->exists()) {
            \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        }
        
        $admin->assignRole('admin');
        
        $user = User::factory()->create();
        $post = Post::factory()->create();

        DB::table('reports')->insert([
            'reporter_id' => $user->id,
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/moderation/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'reason', 'status', 'reportable_type']
                ]
            ]);
    }

    public function test_admin_can_get_moderation_stats(): void
    {
        $admin = User::factory()->create();
        
        // Create admin role if not exists
        if (!\Spatie\Permission\Models\Role::where('name', 'admin')->exists()) {
            \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        }
        
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/moderation/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'reports' => ['total', 'pending', 'reviewed', 'resolved'],
                'content' => ['total_posts', 'flagged_posts', 'total_users', 'suspended_users']
            ]);
    }

    public function test_guest_cannot_report_content(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson('/api/moderation/report', [
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reason' => 'spam'
        ]);

        $response->assertStatus(401);
    }
}