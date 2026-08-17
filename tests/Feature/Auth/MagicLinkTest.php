<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'is_superuser' => false,
        ]);

        $response = $this->post(route('magic-link.send'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, MagicLinkNotification::class);
    }

    public function test_magic_link_request_is_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'is_superuser' => false,
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('magic-link.send'), [
                'email' => $user->email,
            ]);
        }

        $response = $this->post(route('magic-link.send'), [
            'email' => $user->email,
        ]);

        $response->assertTooManyRequests();
    }

    public function test_magic_link_can_be_verified(): void
    {
        $user = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => true,
            'start_person_id' => 'I1',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'magic-link.verify',
            now()->addMinutes(15),
            ['user' => $user->id, 'remember' => '1']
        );

        $response = $this->get($signedUrl);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/gedcom');
    }

    public function test_magic_link_verification_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'is_superuser' => false,
        ]);

        $url = route('magic-link.verify', ['user' => $user->id]);

        for ($i = 0; $i < 6; $i++) {
            $this->get($url);
        }

        $response = $this->get($url);
        $response->assertTooManyRequests();
    }
}
