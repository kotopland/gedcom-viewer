<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GedcomPersonMarriageTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_overview_displays_marriage_for_couple_with_individual_marriage_event()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $cacheFile = storage_path('app/gedcom_parsed.json');
        $backupCache = File::exists($cacheFile) ? File::get($cacheFile) : null;

        $synthData = [
            'stats' => ['total_individuals' => 2, 'total_families' => 1],
            'individuals' => [
                '10275328' => [
                    'id' => '10275328',
                    'name' => 'Jane Doe',
                    'given_name' => 'Jane',
                    'surname' => 'Doe',
                    'all_names' => [
                        [
                            'name' => 'Jane Doe',
                            'given_name' => 'Jane',
                            'surname' => 'Doe',
                            'type' => '',
                        ],
                        [
                            'name' => '',
                            'given_name' => '',
                            'surname' => '',
                            'type' => 'married',
                        ],
                    ],
                    'sex' => 'F',
                    'birth_date' => '15 May 1995',
                    'birth_place' => '',
                    'birth_year' => 1995,
                    'death_date' => '',
                    'death_place' => '',
                    'death_year' => null,
                    'burial_date' => '',
                    'burial_place' => '',
                    'occupation' => null,
                    'fams' => ['85420137'],
                    'famc' => [],
                    'notes' => [],
                    'events' => [
                        [
                            'tag' => 'BIRT',
                            'value' => '',
                            'type' => '',
                            'date' => '15 May 1995',
                            'place' => '',
                            'year' => 1995,
                            'note' => '',
                            'age' => '',
                            'cause' => '',
                        ],
                    ],
                    'spouses' => ['I500262'],
                    'children' => [],
                    'parents' => [],
                    'siblings' => [],
                    'primary_media' => null,
                ],
                'I500262' => [
                    'id' => 'I500262',
                    'name' => 'John Doe',
                    'given_name' => 'John',
                    'surname' => 'Doe',
                    'all_names' => [
                        [
                            'name' => 'John Doe',
                            'given_name' => 'John',
                            'surname' => 'Doe',
                            'type' => '',
                        ],
                    ],
                    'sex' => 'M',
                    'birth_date' => '20 Aug 1993',
                    'birth_place' => 'Oslo, Norway',
                    'birth_year' => 1993,
                    'death_date' => '',
                    'death_place' => '',
                    'death_year' => null,
                    'burial_date' => '',
                    'burial_place' => '',
                    'occupation' => null,
                    'fams' => ['85420137'],
                    'famc' => [],
                    'notes' => [],
                    'events' => [
                        [
                            'tag' => 'BIRT',
                            'value' => '',
                            'type' => '',
                            'date' => '20 Aug 1993',
                            'place' => 'Oslo, Norway',
                            'year' => 1993,
                            'note' => '',
                            'age' => '',
                            'cause' => '',
                        ],
                        [
                            'tag' => 'EVEN',
                            'value' => '',
                            'type' => 'Marriage',
                            'date' => '2026',
                            'place' => '',
                            'year' => 2026,
                            'note' => '',
                            'age' => '',
                            'cause' => '',
                        ],
                    ],
                    'spouses' => ['10275328'],
                    'children' => [],
                    'parents' => [],
                    'siblings' => [],
                    'primary_media' => null,
                ],
            ],
            'families' => [
                '85420137' => [
                    'id' => '85420137',
                    'husband_id' => 'I500262',
                    'wife_id' => '10275328',
                    'children_ids' => [],
                    'marriage_date' => '',
                    'marriage_year' => null,
                    'marriage_place' => '',
                    'relationship_type' => '',
                    'media_ids' => [],
                    'events' => [],
                ],
            ],
            'media' => [],
        ];

        File::put($cacheFile, json_encode($synthData));

        try {
            // 1. Verify wife overview has marriage event and spouse John Doe
            $wifeRes = $this->get('/api/gedcom/person/10275328');
            $wifeRes->assertOk();
            $wifeJson = $wifeRes->json();

            $wifeEvents = $wifeJson['person']['events'];
            $marrEvents = array_values(array_filter($wifeEvents, fn ($e) => ($e['tag'] ?? '') === 'MARR' || ($e['title'] ?? '') === 'Marriage'));
            $this->assertCount(1, $marrEvents, 'Wife should have exactly one marriage event in timeline');
            $this->assertEquals('Marriage', $marrEvents[0]['title']);
            $this->assertEquals(2026, $marrEvents[0]['year']);
            $this->assertNotNull($marrEvents[0]['spouse'], 'Marriage event must have spouse object');
            $this->assertEquals('I500262', $marrEvents[0]['spouse']['id']);
            $this->assertEquals('John Doe', $marrEvents[0]['spouse']['name']);

            // Spouses relation list
            $this->assertCount(1, $wifeJson['relations']['spouses']);
            $this->assertEquals('I500262', $wifeJson['relations']['spouses'][0]['id']);
            $this->assertEquals(2026, $wifeJson['relations']['spouses'][0]['marriage_year']);

            // 2. Verify husband overview has marriage event and spouse Jane Doe without duplicates
            $husbandRes = $this->get('/api/gedcom/person/I500262');
            $husbandRes->assertOk();
            $husbandJson = $husbandRes->json();

            $husbandEvents = $husbandJson['person']['events'];
            $husbandMarrEvents = array_values(array_filter($husbandEvents, fn ($e) => ($e['tag'] ?? '') === 'MARR' || ($e['title'] ?? '') === 'Marriage'));
            $this->assertCount(1, $husbandMarrEvents, 'Husband should have exactly one marriage event in timeline');
            $this->assertEquals('Marriage', $husbandMarrEvents[0]['title']);
            $this->assertEquals(2026, $husbandMarrEvents[0]['year']);
            $this->assertNotNull($husbandMarrEvents[0]['spouse'], 'Husband marriage event must have spouse object');
            $this->assertEquals('10275328', $husbandMarrEvents[0]['spouse']['id']);
            $this->assertEquals('Jane Doe', $husbandMarrEvents[0]['spouse']['name']);
        } finally {
            if ($backupCache !== null) {
                File::put($cacheFile, $backupCache);
            } else {
                File::delete($cacheFile);
            }
        }
    }

    public function test_person_overview_displays_marriage_for_undated_family_couple()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $cacheFile = storage_path('app/gedcom_parsed.json');
        $backupCache = File::exists($cacheFile) ? File::get($cacheFile) : null;

        $synthData = [
            'stats' => ['total_individuals' => 2, 'total_families' => 1],
            'individuals' => [
                'HUSB_1' => [
                    'id' => 'HUSB_1',
                    'name' => 'John Smith',
                    'given_name' => 'John',
                    'surname' => 'Smith',
                    'sex' => 'M',
                    'birth_year' => 1950,
                    'fams' => ['FAM_1'],
                    'famc' => [],
                    'events' => [],
                    'spouses' => ['WIFE_1'],
                    'children' => [],
                    'parents' => [],
                    'siblings' => [],
                    'primary_media' => null,
                ],
                'WIFE_1' => [
                    'id' => 'WIFE_1',
                    'name' => 'Mary Jones',
                    'given_name' => 'Mary',
                    'surname' => 'Jones',
                    'sex' => 'F',
                    'birth_year' => 1952,
                    'fams' => ['FAM_1'],
                    'famc' => [],
                    'events' => [],
                    'spouses' => ['HUSB_1'],
                    'children' => [],
                    'parents' => [],
                    'siblings' => [],
                    'primary_media' => null,
                ],
            ],
            'families' => [
                'FAM_1' => [
                    'id' => 'FAM_1',
                    'husband_id' => 'HUSB_1',
                    'wife_id' => 'WIFE_1',
                    'children_ids' => [],
                    'marriage_date' => '',
                    'marriage_year' => null,
                    'marriage_place' => '',
                    'relationship_type' => '',
                    'events' => [],
                ],
            ],
            'media' => [],
        ];

        File::put($cacheFile, json_encode($synthData));

        try {
            $wifeRes = $this->get('/api/gedcom/person/WIFE_1');
            $wifeRes->assertOk();
            $wifeEvents = $wifeRes->json()['person']['events'];

            $marrEvents = array_values(array_filter($wifeEvents, fn ($e) => ($e['tag'] ?? '') === 'MARR' || ($e['title'] ?? '') === 'Marriage'));
            $this->assertCount(1, $marrEvents, 'Undated marriage should still appear in timeline');
            $this->assertEquals('Marriage', $marrEvents[0]['title']);
            $this->assertNotNull($marrEvents[0]['spouse']);
            $this->assertEquals('HUSB_1', $marrEvents[0]['spouse']['id']);
        } finally {
            if ($backupCache !== null) {
                File::put($cacheFile, $backupCache);
            } else {
                File::delete($cacheFile);
            }
        }
    }

    public function test_active_dataset_couple_marriage()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $cacheFile = storage_path('app/gedcom_parsed.json');
        if (! File::exists($cacheFile)) {
            $this->markTestSkipped('No cached gedcom data');
        }

        // Test couple directly from current dataset if present
        $res = $this->get('/api/gedcom/person/10275328');
        if ($res->status() === 404) {
            $this->markTestSkipped('Person not in current dataset');
        }

        $res->assertOk();
        $json = $res->json();
        $events = $json['person']['events'];
        $marrEvents = array_values(array_filter($events, fn ($e) => ($e['tag'] ?? '') === 'MARR' || ($e['title'] ?? '') === 'Marriage'));
        $this->assertNotEmpty($marrEvents, 'Person must have marriage event in overview');
        $this->assertEquals('I500262', $marrEvents[0]['spouse']['id']);
        $this->assertEquals(2026, $marrEvents[0]['year']);
    }
}
