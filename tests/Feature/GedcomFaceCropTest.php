<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GedcomParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GedcomFaceCropTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(storage_path('app/private'));
        File::ensureDirectoryExists(storage_path('app/public/gedcom/media'));
        File::ensureDirectoryExists(storage_path('app/public/gedcom/crops'));
    }

    public function test_gedcom_parser_extracts_crop_and_first_reference_as_portrait()
    {
        $mediaFile = storage_path('app/public/gedcom/media/test_photo.jpg');

        // Create a 200x100 dummy JPEG image using GD if available
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor(200, 100);
            $red = imagecolorallocate($img, 255, 0, 0);
            imagefill($img, 0, 0, $red);
            imagejpeg($img, $mediaFile);
            imagedestroy($img);
        } else {
            File::put($mediaFile, 'fake_content');
        }

        $gedcomContent = <<<'GEDCOM'
0 HEAD
1 CHAR UTF-8
0 @I100@ INDI
1 NAME John /Doe/
1 SEX M
1 OBJE @M1@
2 CROP
3 TOP 20
3 LEFT 40
3 HEIGHT 50
3 WIDTH 60
1 OBJE @M2@
0 @M1@ OBJE
1 FILE test_photo.jpg
1 FORM image/jpeg
0 @M2@ OBJE
1 FILE other_doc.pdf
1 FORM application/pdf
0 TLR
GEDCOM;

        $tempGed = storage_path('app/private/test_crop.ged');
        File::put($tempGed, $gedcomContent);

        $parser = new GedcomParserService();
        $reflector = new \ReflectionClass($parser);
        $method = $reflector->getMethod('parseRawGedcomLines');
        $method->setAccessible(true);

        $lines = preg_split('/\r\n|\r|\n/', $gedcomContent);
        $data = $method->invoke($parser, $lines);

        $this->assertArrayHasKey('I100', $data['individuals']);
        $person = $data['individuals']['I100'];

        // Verify primary media is the FIRST file reference
        $this->assertNotNull($person['primary_media']);
        $this->assertEquals('M1', $person['primary_media']['id']);
        $this->assertTrue($person['primary_media']['is_portrait']);
        $this->assertEquals('/api/gedcom/person/I100/portrait', $person['portrait_url']);

        // Verify crop structure
        $crop = $person['primary_media']['crop'];
        $this->assertNotNull($crop);
        $this->assertEquals(20, $crop['top']);
        $this->assertEquals(40, $crop['left']);
        $this->assertEquals(50, $crop['height_px']);
        $this->assertEquals(60, $crop['width_px']);

        if (extension_loaded('gd')) {
            // 40 / 200 = 0.2
            $this->assertEquals(0.2, $crop['x']);
            // 20 / 100 = 0.2
            $this->assertEquals(0.2, $crop['y']);
            // 60 / 200 = 0.3
            $this->assertEquals(0.3, $crop['width']);
            // 50 / 100 = 0.5
            $this->assertEquals(0.5, $crop['height']);

            $this->assertEquals('20.00%', $person['primary_media']['css']['left']);
            $this->assertEquals('20.00%', $person['primary_media']['css']['top']);
            $this->assertEquals('30.00%', $person['primary_media']['css']['width']);
            $this->assertEquals('50.00%', $person['primary_media']['css']['height']);
        }

        // Cleanup
        File::delete($tempGed);
        File::delete($mediaFile);
    }

    public function test_portrait_endpoint_serves_cropped_image()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        // If gedcom.ged exists in storage/app/private, test with a real individual who has a crop
        $realGed = storage_path('app/private/gedcom.ged');
        if (File::exists($realGed)) {
            $parser = app(GedcomParserService::class);
            $data = $parser->parseAndCache();

            // Check Olav Topland (I500194) who has crop on 57305632
            if (isset($data['individuals']['I500194'])) {
                $olav = $data['individuals']['I500194'];
                $this->assertNotNull($olav['primary_media']);
                $this->assertEquals('57305632', $olav['primary_media']['id']);
                $this->assertNotNull($olav['primary_media']['crop']);

                $response = $this->get(route('gedcom.api.person.portrait', ['id' => 'I500194']));
                $response->assertOk();
                $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
            }
        }
    }

    public function test_superuser_can_download_face_export_script()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $response = $this->get(route('gedcom.api.download-face-script'));
        $response->assertOk();
        $this->assertStringContainsString('mft11_export_faces.py', (string)$response->headers->get('Content-Disposition'));
    }

    public function test_superuser_can_upload_faces_json()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $realFaces = storage_path('app/private/faces.json');
        $backupFaces = File::exists($realFaces) ? File::get($realFaces) : null;

        try {
            $sampleJson = json_encode([
                'by_person' => [
                    'I100' => [
                        [
                            'person_id' => 'I100',
                            'person_name' => 'John Doe',
                            'media_id' => 'M1',
                            'crop' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.5, 'height' => 0.5],
                            'css' => ['left' => '10%', 'top' => '10%', 'width' => '50%', 'height' => '50%'],
                        ],
                    ],
                ],
                'all_tags' => [
                    [
                        'person_id' => 'I100',
                        'person_name' => 'John Doe',
                        'media_id' => 'M1',
                    ],
                ],
            ]);

            $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('faces.json', $sampleJson);

            $response = $this->postJson(route('gedcom.api.upload-faces'), [
                'file' => $file,
            ]);

            $response->assertOk();
            $response->assertJson([
                'message' => 'Successfully imported 1 face tag(s) into the family tree.',
                'total_faces' => 1,
            ]);

            $this->assertFileExists($realFaces);
        } finally {
            if ($backupFaces !== null) {
                File::put($realFaces, $backupFaces);
            }
        }
    }

    public function test_guests_cannot_access_superuser_face_endpoints()
    {
        $this->postJson(route('gedcom.api.upload-faces'))
            ->assertUnauthorized();

        $this->get(route('gedcom.api.download-face-script'))
            ->assertUnauthorized();
    }

    public function test_non_superuser_cannot_access_superuser_face_endpoints()
    {
        $user = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => true,
        ]);
        $this->actingAs($user);

        $this->postJson(route('gedcom.api.upload-faces'))
            ->assertForbidden();

        $this->get(route('gedcom.api.download-face-script'))
            ->assertForbidden();
    }

    public function test_upload_faces_validates_file_extension()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('faces.txt', 'not json');

        $response = $this->postJson(route('gedcom.api.upload-faces'), [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_upload_faces_rejects_malformed_json_content()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $invalidJson = json_encode(['foo' => 'bar', 'some_other_key' => 123]);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('faces.json', $invalidJson);

        $response = $this->postJson(route('gedcom.api.upload-faces'), [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Invalid faces.json format. Expected JSON containing "by_person" or "all_tags".',
        ]);
    }

    public function test_gedcom_parser_falls_back_to_faces_json_when_no_crop_node()
    {
        $mediaFile = storage_path('app/public/gedcom/media/fallback_photo.jpg');
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor(100, 100);
            imagefill($img, 0, 0, imagecolorallocate($img, 0, 255, 0));
            imagejpeg($img, $mediaFile);
            imagedestroy($img);
        } else {
            File::put($mediaFile, 'fake_img');
        }

        $facesPath = storage_path('app/private/faces.json');
        $backupFaces = File::exists($facesPath) ? File::get($facesPath) : null;

        $sampleFaces = json_encode([
            'by_person' => [
                'I200' => [
                    [
                        'person_id' => 'I200',
                        'media_id' => 'M20',
                        'filename' => 'fallback_photo.jpg',
                        'crop' => ['x' => 0.15, 'y' => 0.25, 'width' => 0.35, 'height' => 0.45],
                        'css' => ['left' => '15.00%', 'top' => '25.00%', 'width' => '35.00%', 'height' => '45.00%'],
                    ],
                ],
            ],
        ]);
        File::put($facesPath, $sampleFaces);

        $gedcomContent = <<<'GEDCOM'
0 HEAD
1 CHAR UTF-8
0 @I200@ INDI
1 NAME Fallback /Person/
1 SEX F
1 OBJE @M20@
0 @M20@ OBJE
1 FILE fallback_photo.jpg
1 FORM image/jpeg
0 TLR
GEDCOM;

        $parser = new GedcomParserService();
        $reflector = new \ReflectionClass($parser);
        $method = $reflector->getMethod('parseRawGedcomLines');
        $method->setAccessible(true);

        try {
            $lines = preg_split('/\r\n|\r|\n/', $gedcomContent);
            $data = $method->invoke($parser, $lines);

            $this->assertArrayHasKey('I200', $data['individuals']);
            $person = $data['individuals']['I200'];

            $this->assertNotNull($person['primary_media']);
            $this->assertTrue($person['primary_media']['is_portrait']);
            $this->assertNotNull($person['primary_media']['crop']);
            $this->assertEquals(0.15, $person['primary_media']['crop']['x']);
            $this->assertEquals(0.25, $person['primary_media']['crop']['y']);
            $this->assertEquals('15.00%', $person['primary_media']['css']['left']);
        } finally {
            if ($backupFaces !== null) {
                File::put($facesPath, $backupFaces);
            } else {
                File::delete($facesPath);
            }
            File::delete($mediaFile);
        }
    }

    public function test_multiple_individuals_tagged_in_same_photo_populates_obj_faces()
    {
        $mediaFile = storage_path('app/public/gedcom/media/group_photo.jpg');
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor(400, 200);
            imagefill($img, 0, 0, imagecolorallocate($img, 0, 0, 255));
            imagejpeg($img, $mediaFile);
            imagedestroy($img);
        } else {
            File::put($mediaFile, 'fake_img');
        }

        $gedcomContent = <<<'GEDCOM'
0 HEAD
1 CHAR UTF-8
0 @I10@ INDI
1 NAME Alice /Smith/
1 SEX F
1 OBJE @M99@
2 CROP
3 TOP 20
3 LEFT 20
3 HEIGHT 60
3 WIDTH 60
0 @I20@ INDI
1 NAME Bob /Smith/
1 SEX M
1 OBJE @M99@
2 CROP
3 TOP 20
3 LEFT 120
3 HEIGHT 60
3 WIDTH 60
0 @M99@ OBJE
1 FILE group_photo.jpg
1 FORM image/jpeg
0 TLR
GEDCOM;

        $parser = new GedcomParserService();
        $reflector = new \ReflectionClass($parser);
        $method = $reflector->getMethod('parseRawGedcomLines');
        $method->setAccessible(true);

        try {
            $lines = preg_split('/\r\n|\r|\n/', $gedcomContent);
            $data = $method->invoke($parser, $lines);

            $this->assertArrayHasKey('M99', $data['objects']);
            $media = $data['objects']['M99'];

            $this->assertArrayHasKey('faces', $media);
            $this->assertCount(2, $media['faces']);

            $aliceFace = $media['faces'][0];
            $this->assertEquals('I10', $aliceFace['person_id']);
            $this->assertEquals('Alice Smith', $aliceFace['person_name']);
            $this->assertEquals('/api/gedcom/person/I10/portrait', $aliceFace['portrait_url']);

            $bobFace = $media['faces'][1];
            $this->assertEquals('I20', $bobFace['person_id']);
            $this->assertEquals('Bob Smith', $bobFace['person_name']);
            $this->assertEquals('/api/gedcom/person/I20/portrait', $bobFace['portrait_url']);
        } finally {
            File::delete($mediaFile);
        }
    }

    public function test_portrait_endpoint_serves_cropped_image_self_contained()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $mediaFile = storage_path('app/public/gedcom/media/synth_portrait.jpg');
        $cropFile = storage_path('app/public/gedcom/crops/ISYNTH_synth_portrait.jpg');

        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor(200, 200);
            $yellow = imagecolorallocate($img, 255, 255, 0);
            imagefill($img, 0, 0, $yellow);
            imagejpeg($img, $mediaFile);
            imagedestroy($img);
        } else {
            File::put($mediaFile, 'fake_img');
        }

        $cacheFile = storage_path('app/gedcom_parsed.json');
        $backupCache = File::exists($cacheFile) ? File::get($cacheFile) : null;

        $synthData = [
            'stats' => ['total_individuals' => 1, 'total_families' => 0],
            'individuals' => [
                'ISYNTH' => [
                    'id' => 'ISYNTH',
                    'name' => 'Synthetic Person',
                    'sex' => 'M',
                    'primary_media' => [
                        'id' => 'MSYNTH',
                        'file' => 'synth_portrait.jpg',
                        'mime' => 'image/jpeg',
                        'url' => '/storage/gedcom/media/synth_portrait.jpg',
                        'is_portrait' => true,
                        'portrait_url' => '/api/gedcom/person/ISYNTH/portrait',
                        'crop' => [
                            'x' => 0.1,
                            'y' => 0.1,
                            'width' => 0.5,
                            'height' => 0.5,
                            'top' => 20,
                            'left' => 20,
                            'width_px' => 100,
                            'height_px' => 100,
                        ],
                    ],
                    'portrait_url' => '/api/gedcom/person/ISYNTH/portrait',
                ],
            ],
            'families' => [],
            'media' => [],
        ];

        File::put($cacheFile, json_encode($synthData));

        try {
            $response = $this->get(route('gedcom.api.person.portrait', ['id' => 'ISYNTH']));
            $response->assertOk();
            $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));

            if (extension_loaded('gd')) {
                $this->assertFileExists($cropFile);
            }
        } finally {
            if ($backupCache !== null) {
                File::put($cacheFile, $backupCache);
            } else {
                File::delete($cacheFile);
            }
            File::delete($mediaFile);
            File::delete($cropFile);
        }
    }

    public function test_portrait_endpoint_serves_original_when_no_crop()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $mediaFile = storage_path('app/public/gedcom/media/synth_nocrop.jpg');
        File::put($mediaFile, 'dummy_jpeg_data');

        $cacheFile = storage_path('app/gedcom_parsed.json');
        $backupCache = File::exists($cacheFile) ? File::get($cacheFile) : null;

        $synthData = [
            'stats' => ['total_individuals' => 1, 'total_families' => 0],
            'individuals' => [
                'INOCROP' => [
                    'id' => 'INOCROP',
                    'name' => 'No Crop Person',
                    'sex' => 'F',
                    'primary_media' => [
                        'id' => 'MNOCROP',
                        'file' => 'synth_nocrop.jpg',
                        'mime' => 'image/jpeg',
                        'url' => '/storage/gedcom/media/synth_nocrop.jpg',
                        'is_portrait' => true,
                        'portrait_url' => '/api/gedcom/person/INOCROP/portrait',
                    ],
                    'portrait_url' => '/api/gedcom/person/INOCROP/portrait',
                ],
            ],
            'families' => [],
            'media' => [],
        ];

        File::put($cacheFile, json_encode($synthData));

        try {
            $response = $this->get(route('gedcom.api.person.portrait', ['id' => 'INOCROP']));
            $response->assertOk();
            $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        } finally {
            if ($backupCache !== null) {
                File::put($cacheFile, $backupCache);
            } else {
                File::delete($cacheFile);
            }
            File::delete($mediaFile);
        }
    }

    public function test_portrait_endpoint_returns_404_when_person_not_found()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $response = $this->get(route('gedcom.api.person.portrait', ['id' => 'NON_EXISTENT_ID']));
        $response->assertNotFound();
    }

    public function test_serve_crop_media_serves_cached_file()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $cropFile = storage_path('app/public/gedcom/crops/cached_test.jpg');
        File::put($cropFile, 'fake_cached_image');

        try {
            $response = $this->get(route('gedcom.storage.crops', ['filename' => 'cached_test.jpg']));
            $response->assertOk();
            $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        } finally {
            File::delete($cropFile);
        }
    }

    public function test_serve_crop_media_returns_404_for_missing_file()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $response = $this->get(route('gedcom.storage.crops', ['filename' => 'non_existent_crop.jpg']));
        $response->assertNotFound();
    }
}
