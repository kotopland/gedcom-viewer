<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperuserVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_webapp(): void
    {
        $response = $this->get('/gedcom');

        $response->assertRedirect('/login');
    }

    public function test_unverified_normal_user_is_redirected_to_pending_verification(): void
    {
        $user = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => false,
        ]);

        $response = $this->actingAs($user)->get('/gedcom');

        $response->assertRedirect(route('verification.pending'));
    }

    public function test_unverified_normal_user_receives_403_on_api(): void
    {
        $user = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/gedcom/search');

        $response->assertStatus(403);
    }

    public function test_verified_normal_user_can_access_webapp(): void
    {
        $user = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => true,
        ]);

        $response = $this->actingAs($user)->get('/gedcom');

        $response->assertStatus(200);
    }

    public function test_superuser_can_access_webapp_and_admin_panel(): void
    {
        $superuser = User::factory()->create([
            'is_superuser' => true,
            'is_verified' => true,
        ]);

        $response = $this->actingAs($superuser)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_normal_user_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_superuser_can_verify_a_pending_user(): void
    {
        $superuser = User::factory()->create([
            'is_superuser' => true,
            'is_verified' => true,
        ]);

        $pendingUser = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => false,
        ]);

        $response = $this->actingAs($superuser)->patch(route('admin.users.verify', $pendingUser));

        $response->assertRedirect();
        $this->assertTrue($pendingUser->fresh()->is_verified);
    }

    public function test_superuser_can_unverify_a_normal_user(): void
    {
        $superuser = User::factory()->create([
            'is_superuser' => true,
            'is_verified' => true,
        ]);

        $normalUser = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => true,
        ]);

        $response = $this->actingAs($superuser)->patch(route('admin.users.unverify', $normalUser));

        $response->assertRedirect();
        $this->assertFalse($normalUser->fresh()->is_verified);
    }
}
