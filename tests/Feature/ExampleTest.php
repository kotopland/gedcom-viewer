<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $user = \App\Models\User::factory()->verified()->create(['start_person_id' => 'I1']);
        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('gedcom.index'));
    }

}
