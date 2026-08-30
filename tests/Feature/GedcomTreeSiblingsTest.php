<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GedcomTreeSiblingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_endpoint_returns_siblings_with_partners_and_descendants()
    {
        $superuser = User::factory()->superuser()->create(['is_verified' => true]);
        $this->actingAs($superuser);

        $cacheFile = storage_path('app/gedcom_parsed.json');
        $backupCache = File::exists($cacheFile) ? File::get($cacheFile) : null;

        // Family setup:
        // Parents: FATHER, MOTHER
        // Children: SIBLING_1 (born 1920), FOCUS_PERSON (born 1925), SIBLING_2 (born 1930)
        // SIBLING_1 married to SPOUSE_SIB1, child NIECE_1
        // SIBLING_2 married to SPOUSE_SIB2, child NEPHEW_1
        // FOCUS_PERSON married to SPOUSE_FOCUS, child CHILD_1
        $synthData = [
            'stats' => ['total_individuals' => 9, 'total_families' => 4],
            'individuals' => [
                'FATHER' => [
                    'id' => 'FATHER',
                    'name' => 'John Doe',
                    'sex' => 'M',
                    'birth_year' => 1890,
                    'parents' => [],
                    'spouses' => ['MOTHER'],
                    'children' => ['SIBLING_1', 'FOCUS_PERSON', 'SIBLING_2'],
                    'primary_media' => null,
                ],
                'MOTHER' => [
                    'id' => 'MOTHER',
                    'name' => 'Jane Doe',
                    'sex' => 'F',
                    'birth_year' => 1895,
                    'parents' => [],
                    'spouses' => ['FATHER'],
                    'children' => ['SIBLING_1', 'FOCUS_PERSON', 'SIBLING_2'],
                    'primary_media' => null,
                ],
                'SIBLING_1' => [
                    'id' => 'SIBLING_1',
                    'name' => 'Anna Doe',
                    'sex' => 'F',
                    'birth_year' => 1920,
                    'parents' => ['FATHER', 'MOTHER'],
                    'spouses' => ['SPOUSE_SIB1'],
                    'children' => ['NIECE_1'],
                    'primary_media' => null,
                ],
                'SPOUSE_SIB1' => [
                    'id' => 'SPOUSE_SIB1',
                    'name' => 'Bob Smith',
                    'sex' => 'M',
                    'birth_year' => 1918,
                    'parents' => [],
                    'spouses' => ['SIBLING_1'],
                    'children' => ['NIECE_1'],
                    'primary_media' => null,
                ],
                'NIECE_1' => [
                    'id' => 'NIECE_1',
                    'name' => 'Emily Smith',
                    'sex' => 'F',
                    'birth_year' => 1945,
                    'parents' => ['SIBLING_1', 'SPOUSE_SIB1'],
                    'spouses' => [],
                    'children' => [],
                    'primary_media' => null,
                ],
                'FOCUS_PERSON' => [
                    'id' => 'FOCUS_PERSON',
                    'name' => 'Arne Doe',
                    'sex' => 'M',
                    'birth_year' => 1925,
                    'parents' => ['FATHER', 'MOTHER'],
                    'spouses' => ['SPOUSE_FOCUS'],
                    'children' => ['CHILD_1'],
                    'primary_media' => null,
                ],
                'SPOUSE_FOCUS' => [
                    'id' => 'SPOUSE_FOCUS',
                    'name' => 'Clara White',
                    'sex' => 'F',
                    'birth_year' => 1927,
                    'parents' => [],
                    'spouses' => ['FOCUS_PERSON'],
                    'children' => ['CHILD_1'],
                    'primary_media' => null,
                ],
                'CHILD_1' => [
                    'id' => 'CHILD_1',
                    'name' => 'David Doe',
                    'sex' => 'M',
                    'birth_year' => 1955,
                    'parents' => ['FOCUS_PERSON', 'SPOUSE_FOCUS'],
                    'spouses' => [],
                    'children' => [],
                    'primary_media' => null,
                ],
                'SIBLING_2' => [
                    'id' => 'SIBLING_2',
                    'name' => 'Carl Doe',
                    'sex' => 'M',
                    'birth_year' => 1930,
                    'parents' => ['FATHER', 'MOTHER'],
                    'spouses' => ['SPOUSE_SIB2'],
                    'children' => ['NEPHEW_1'],
                    'primary_media' => null,
                ],
                'SPOUSE_SIB2' => [
                    'id' => 'SPOUSE_SIB2',
                    'name' => 'Grace Lee',
                    'sex' => 'F',
                    'birth_year' => 1932,
                    'parents' => [],
                    'spouses' => ['SIBLING_2'],
                    'children' => ['NEPHEW_1'],
                    'primary_media' => null,
                ],
                'NEPHEW_1' => [
                    'id' => 'NEPHEW_1',
                    'name' => 'Frank Doe',
                    'sex' => 'M',
                    'birth_year' => 1960,
                    'parents' => ['SIBLING_2', 'SPOUSE_SIB2'],
                    'spouses' => [],
                    'children' => [],
                    'primary_media' => null,
                ],
            ],
            'families' => [],
            'media' => [],
        ];

        File::put($cacheFile, json_encode($synthData));

        try {
            $response = $this->get('/api/gedcom/tree/FOCUS_PERSON?ancestors=1&descendants=2');
            $response->assertOk();

            $json = $response->json();
            $this->assertEquals('FOCUS_PERSON', $json['primary']['id']);
            $this->assertCount(1, $json['primary']['children']);
            $this->assertEquals('CHILD_1', $json['primary']['children'][0]['id']);

            // Siblings
            $this->assertArrayHasKey('siblings', $json);
            $this->assertCount(2, $json['siblings']);

            // Chronological order: SIBLING_1 (1920) then SIBLING_2 (1930)
            $sib1 = $json['siblings'][0];
            $this->assertEquals('SIBLING_1', $sib1['id']);
            $this->assertCount(1, $sib1['spouses']);
            $this->assertEquals('SPOUSE_SIB1', $sib1['spouses'][0]['id']);
            $this->assertCount(1, $sib1['children']);
            $this->assertEquals('NIECE_1', $sib1['children'][0]['id']);

            $sib2 = $json['siblings'][1];
            $this->assertEquals('SIBLING_2', $sib2['id']);
            $this->assertCount(1, $sib2['spouses']);
            $this->assertEquals('SPOUSE_SIB2', $sib2['spouses'][0]['id']);
            $this->assertCount(1, $sib2['children']);
            $this->assertEquals('NEPHEW_1', $sib2['children'][0]['id']);
        } finally {
            if ($backupCache !== null) {
                File::put($cacheFile, $backupCache);
            } else {
                File::delete($cacheFile);
            }
        }
    }
}
