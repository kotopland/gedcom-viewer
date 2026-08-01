<?php

namespace Tests\Feature;

use App\Mail\PersonContributionSubmittedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonContributionTest extends TestCase
{

    use RefreshDatabase;

    public function test_user_can_submit_note_contribution(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        Mail::fake();

        $superuser = User::factory()->superuser()->create([
            'email' => 'admin@example.com',
        ]);


        $user = User::factory()->verified()->create([
            'name' => 'John Contributor',
        ]);

        $response = $this->actingAs($user)->postJson('/api/gedcom/person/I1/contribution', [
            'note' => 'Born in Oslo in 1895 according to church registry.',
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Thank you! Your note/media file has been submitted successfully to the administrator.',
        ]);

        Mail::assertSent(PersonContributionSubmittedMail::class, function ($mail) use ($user) {
            return $mail->sender->id === $user->id
                && $mail->note === 'Born in Oslo in 1895 according to church registry.'
                && $mail->hasTo('admin@example.com');
        });
    }

    public function test_user_can_submit_media_file_contribution(): void
    {
        Mail::fake();
        Storage::fake('public');

        $superuser = User::factory()->superuser()->create([
            'email' => 'admin@example.com',
        ]);

        $user = User::factory()->verified()->create();

        $file = UploadedFile::fake()->image('old_photo.jpg');

        $response = $this->actingAs($user)->postJson('/api/gedcom/person/I1/contribution', [
            'note' => 'Family photo from 1920',
            'media' => $file,
        ]);

        $response->assertOk();

        Mail::assertSent(PersonContributionSubmittedMail::class, function ($mail) use ($user) {
            return $mail->sender->id === $user->id
                && !empty($mail->mediaUrl)
                && $mail->mediaOriginalName === 'old_photo.jpg';
        });
    }
}
