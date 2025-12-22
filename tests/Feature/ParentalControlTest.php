<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentalControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_child()
    {
        $parent = User::factory()->create();
        $child = User::factory()->create(['is_child' => true]);

        $response = $this->actingAs($parent)->postJson('/api/parental/link-child', [
            'child_email' => $child->email,
        ]);

        $response->assertStatus(201);
    }

    public function test_approve_link()
    {
        $this->assertTrue(true);
    }

    public function test_get_child_activity()
    {
        $this->assertTrue(true);
    }
}
