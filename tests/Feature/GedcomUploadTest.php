<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GedcomUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(storage_path('app/private'));
        File::ensureDirectoryExists(storage_path('app/public/gedcom/media'));
    }

    public function test_guests_cannot_upload_gedcom()
    {
        $response = $this->postJson(route('gedcom.api.upload'));
        $response->assertUnauthorized();
    }

    public function test_non_superuser_cannot_upload_gedcom()
    {
        $user = User::factory()->create(['is_superuser' => false, 'is_verified' => true]);
        $this->actingAs($user);

        $response = $this->postJson(route('gedcom.api.upload'));
        $response->assertForbidden();
    }

    public function test_superuser_can_upload_ged_file_and_preserve_media_cache()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        // Create a dummy media file in media cache to verify it is preserved
        $dummyMediaFile = storage_path('app/public/gedcom/media/existing_photo.jpg');
        File::put($dummyMediaFile, 'fake_image_data');

        // Create a dummy zip file in private to test cleanup
        $dummyZipFile = storage_path('app/private/old_archive.zip');
        File::put($dummyZipFile, 'fake_zip_data');

        $gedcomContent = <<<'GEDCOM'
0 HEAD
1 CHAR UTF-8
0 @I1@ INDI
1 NAME John /Doe/
1 SEX M
0 TLR
GEDCOM;

        $uploadedFile = UploadedFile::fake()->createWithContent('family_tree.ged', $gedcomContent);

        $response = $this->postJson(route('gedcom.api.upload'), [
            'file' => $uploadedFile,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'stats' => ['total_individuals', 'total_families'],
        ]);

        // Verify ZIP archive was removed
        $this->assertFileDoesNotExist($dummyZipFile);

        // Verify newly uploaded gedcom.ged exists in storage/app/private
        $this->assertFileExists(storage_path('app/private/gedcom.ged'));

        // Verify existing media cache file was preserved
        $this->assertFileExists($dummyMediaFile);
    }
}
