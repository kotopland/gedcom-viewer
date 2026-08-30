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
                    'name' => 'Erika Kalra',
                    'given_name' => 'Erika',
                    'surname' => 'Kalra',
                    'all_names' => [
                        [
                            'name' => 'Erika Kalra',
                            'given_name' => 'Erika',
                            'surname' => 'Kalra',
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
                    'birth_date' => '31 Oct 1997',
                    'birth_place' => '',
                    'birth_year' => 1997,
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
                            'date' => '31 Oct 1997',
                            'place' => '',
                            'year' => 1997,
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
                    'name' => 'Even Topland',
                    'given_name' => 'Even',
                    'surname' => 'Topland',
                    'all_names' => [
                        [
                            'name' => 'Even Topland',
                            'given_name' => 'Even',
                            'surname' => 'Topland',
                            'type' => '',
                        ],
                    ],
                    'sex' => 'M',
                    'birth_date' => '09 Nov 1997',
                    'birth_place' => 'Suzuka, Mie Prefecture, Japan',
                    'birth_year' => 1997,
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
                            'date' => '09 Nov 1997',
                            'place' => 'Suzuka, Mie Prefecture, Japan',
                            'year' => 1997,
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
            // 1. Verify Erika's overview has marriage event and spouse Even Topland
            $erikaRes = $this->get('/api/gedcom/person/10275328');
            $erikaRes->assertOk();
            $erikaJson = $erikaRes->json();

            $erikaEvents = $erikaJson['person']['events'];
            $marrEvents = array_values(array_filter($erikaEvents, fn ($e) => ($e['tag'] ?? '') === 'MARR' || ($e['title'] ?? '') === 'Marriage'));
            $this->assertCount(1, $marrEvents, 'Erika should have exactly one marriage event in timeline');
            $this->assertEquals('Marriage', $marrEvents[0]['title']);
            $this->assertEquals(2026, $marrEvents[0]['year']);
            $this->assertNotNull($marrEvents[0]['spouse'], 'Marriage event must have spouse object');
            $this->assertEquals('I500262', $marrEvents[0]['spouse']['id']);
            $this->assertEquals('Even Topland', $marrEvents[0]['spouse']['name']);

            // Spouses relation list
            $this->assertCount(1, $erikaJson['relations']['spouses']);
            $this->assertEquals('I500262', $erikaJson['relations']['spouses'][0]['id']);
            $this->assertEquals(2026, $erikaJson['relations']['spouses'][0]['marriage_year']);

            // 2. Verify Even's overview has marriage event and spouse Erika Kalra without duplicates
            $evenRes = $this->get('/api/gedcom/person/I500262');
            $evenRes->assertOk();
            $evenJson = $evenRes->json();

            $evenEvents = $evenJson['person']['events'];
            $evenMarrEvents = array_values(array_filter($evenEvents, fn ($e) => ($e['tag'] ?? '') === 'MARR' || ($e['title'] ?? '') === 'Marriage'));
            $this->assertCount(1, $evenMarrEvents, 'Even should have exactly one marriage event in timeline');
            $this->assertEquals('Marriage', $evenMarrEvents[0]['title']);
            $this->assertEquals(2026, $evenMarrEvents[0]['year']);
            $this->assertNotNull($evenMarrEvents[0]['spouse'], 'Even marriage event must have spouse object');
            $this->assertEquals('10275328', $evenMarrEvents[0]['spouse']['id']);
            $this->assertEquals('Erika Kalra', $evenMarrEvents[0]['spouse']['name']);
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

    public function test_active_dataset_erika_and_even_marriage()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $cacheFile = storage_path('app/gedcom_parsed.json');
        if (!File::exists($cacheFile)) {
            $this->markTestSkipped('No cached gedcom data');
        }

        // Test Erika directly from current dataset
        $res = $this->get('/api/gedcom/person/10275328');
        if ($res->status() === 404) {
            $this->markTestSkipped('Erika not in current dataset');
        }

        $res->assertOk();
        $json = $res->json();
        $events = $json['person']['events'];
        $marrEvents = array_values(array_filter($events, fn ($e) => ($e['tag'] ?? '') === 'MARR' || ($e['title'] ?? '') === 'Marriage'));
        $this->assertNotEmpty($marrEvents, 'Erika must have marriage event in overview');
        $this->assertEquals('I500262', $marrEvents[0]['spouse']['id']);
        $this->assertEquals(2026, $marrEvents[0]['year']);
    }
}
