<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class GedcomZipReimportTest extends TestCase
{
    use RefreshDatabase;

    protected string $testZipPath;
    protected string $staleMedia;
    protected string $staleCrop;
    protected ?string $backupGed = null;
    protected ?string $backupFaces = null;
    protected ?string $backupCache = null;

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(storage_path('app/private'));
        File::ensureDirectoryExists(storage_path('app/public/gedcom/media'));
        File::ensureDirectoryExists(storage_path('app/public/gedcom/crops'));

        $this->testZipPath = storage_path('app/private/test_reimport_archive.zip');
        $this->staleMedia = storage_path('app/public/gedcom/media/stale_old_photo.jpg');
        $this->staleCrop = storage_path('app/public/gedcom/crops/stale_old_crop.jpg');

        $realGed = storage_path('app/private/gedcom.ged');
        $realFaces = storage_path('app/private/faces.json');
        $realCache = storage_path('app/gedcom_parsed.json');

        $this->backupGed = File::exists($realGed) ? File::get($realGed) : null;
        $this->backupFaces = File::exists($realFaces) ? File::get($realFaces) : null;
        $this->backupCache = File::exists($realCache) ? File::get($realCache) : null;
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testZipPath)) {
            File::delete($this->testZipPath);
        }
        if (File::exists($this->staleMedia)) {
            File::delete($this->staleMedia);
        }
        if (File::exists($this->staleCrop)) {
            File::delete($this->staleCrop);
        }
        $testAlice = storage_path('app/public/gedcom/media/alice_portrait.jpg');
        if (File::exists($testAlice)) {
            File::delete($testAlice);
        }

        $realGed = storage_path('app/private/gedcom.ged');
        $realFaces = storage_path('app/private/faces.json');
        $realCache = storage_path('app/gedcom_parsed.json');

        if ($this->backupGed !== null) {
            File::put($realGed, $this->backupGed);
        }
        if ($this->backupFaces !== null) {
            File::put($realFaces, $this->backupFaces);
        }
        if ($this->backupCache !== null) {
            File::put($realCache, $this->backupCache);
        }

        parent::tearDown();
    }

    public function test_guests_cannot_trigger_reimport()
    {
        $response = $this->postJson(route('gedcom.api.reimport'));
        $response->assertUnauthorized();
    }

    public function test_non_superusers_cannot_trigger_reimport()
    {
        $user = User::factory()->create(['is_superuser' => false, 'is_verified' => true]);
        $this->actingAs($user);

        $response = $this->postJson(route('gedcom.api.reimport'));
        $response->assertForbidden();
    }

    public function test_zip_reimport_wipes_old_tree_data_and_imports_gedcom_media_and_faces()
    {
        $superuser = User::factory()->superuser()->create([
            'email' => 'admin@topland.family',
            'is_verified' => true,
        ]);
        $normalUser = User::factory()->create([
            'email' => 'member@topland.family',
            'is_verified' => true,
            'start_person_id' => 'I100',
        ]);
        $this->actingAs($superuser);

        // 1. Seed stale tree data to verify it gets wiped
        File::put($this->staleMedia, 'stale_media_content');
        File::put($this->staleCrop, 'stale_crop_content');
        $cachePath = storage_path('app/gedcom_parsed.json');
        File::put($cachePath, json_encode(['stale' => true]));

        // 2. Create an image for the test photo
        $imageContent = 'fake_image_bytes';
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor(100, 100);
            imagefill($img, 0, 0, imagecolorallocate($img, 50, 100, 150));
            ob_start();
            imagejpeg($img);
            $imageContent = ob_get_clean();
            imagedestroy($img);
        }

        // 3. Build a test ZIP file containing gedcom.ged, faces.json, and the photo
        $gedcomContent = <<<'GEDCOM'
0 HEAD
1 CHAR UTF-8
0 @I100@ INDI
1 NAME Alice /Topland/
1 SEX F
1 OBJE @M1@
0 @M1@ OBJE
1 FILE alice_portrait.jpg
1 FORM image/jpeg
0 TLR
GEDCOM;

        $facesJsonContent = json_encode([
            'by_person' => [
                'I100' => [
                    [
                        'person_id' => 'I100',
                        'media_id' => 'M1',
                        'filename' => 'alice_portrait.jpg',
                        'crop' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.4, 'height' => 0.4],
                        'css' => ['left' => '10.00%', 'top' => '10.00%', 'width' => '40.00%', 'height' => '40.00%'],
                    ],
                ],
            ],
        ]);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->testZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('gedcom.ged', $gedcomContent);
        $zip->addFromString('faces.json', $facesJsonContent);
        $zip->addFromString('alice_portrait.jpg', $imageContent);
        $zip->close();

        // 4. Trigger ZIP Archive Re-import
        $response = $this->postJson(route('gedcom.api.reimport'));
        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'stats' => ['total_individuals', 'total_families'],
        ]);

        // 5. Verify stale tree data was deleted
        $this->assertFileDoesNotExist($this->staleMedia);
        $this->assertFileDoesNotExist($this->staleCrop);

        // 6. Verify gedcom.ged and faces.json were imported to storage/app/private
        $this->assertFileExists(storage_path('app/private/gedcom.ged'));
        $this->assertFileExists(storage_path('app/private/faces.json'));
        $this->assertStringContainsString('Alice /Topland/', File::get(storage_path('app/private/gedcom.ged')));
        $this->assertStringContainsString('alice_portrait.jpg', File::get(storage_path('app/private/faces.json')));

        // 7. Verify media was extracted to public storage
        $this->assertFileExists(storage_path('app/public/gedcom/media/alice_portrait.jpg'));

        // 8. Verify faces.json was applied to the parsed individual
        $parsedData = json_decode(File::get($cachePath), true);
        $this->assertArrayHasKey('I100', $parsedData['individuals']);
        $alice = $parsedData['individuals']['I100'];
        $this->assertNotNull($alice['primary_media']);
        $this->assertTrue($alice['primary_media']['is_portrait']);
        $this->assertEquals(0.1, $alice['primary_media']['crop']['x']);
        $this->assertEquals('10.00%', $alice['primary_media']['css']['left']);

        // 9. Verify that Laravel users in database were completely untouched
        $this->assertDatabaseHas('users', [
            'id' => $superuser->id,
            'email' => 'admin@topland.family',
            'is_superuser' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $normalUser->id,
            'email' => 'member@topland.family',
            'start_person_id' => 'I100',
        ]);
    }
}
