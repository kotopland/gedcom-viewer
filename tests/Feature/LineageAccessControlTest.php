<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LineagePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineageAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_lineage_permission_service_computes_direct_lineage_and_siblings(): void
    {

        $service = new LineagePermissionService();

        // Sample GEDCOM individuals graph:
        // I1 (Start Person)
        // Parents of I1: P1 (Father), P2 (Mother)
        // Siblings of I1: S1 (Brother)
        // Children of I1: C1 (Son)
        // Siblings of P1: S_P1 (Uncle)
        // Siblings of C1: S_C1 (Daughter 2, i.e., also child of I1)
        // Unrelated: U1
        $mockData = [
            'I1' => [
                'id' => 'I1',
                'name' => 'Start Person',
                'parents' => ['P1', 'P2'],
                'children' => ['C1'],
                'siblings' => ['S1'],
                'spouses' => ['SP_I1'],
            ],
            'SP_I1' => [
                'id' => 'SP_I1',
                'name' => 'Spouse of Start Person',
                'parents' => [],
                'children' => ['C1'],
                'siblings' => [],
                'spouses' => ['I1'],
            ],
            'P1' => [
                'id' => 'P1',
                'name' => 'Father',
                'parents' => [],
                'children' => ['I1', 'S1'],
                'siblings' => ['S_P1'],
            ],
            'P2' => [
                'id' => 'P2',
                'name' => 'Mother',
                'parents' => [],
                'children' => ['I1', 'S1'],
                'siblings' => [],
            ],
            'S1' => [
                'id' => 'S1',
                'name' => 'Brother',
                'parents' => ['P1', 'P2'],
                'children' => ['N1'],
                'siblings' => ['I1'],
                'spouses' => ['SP_S1'],
            ],
            'SP_S1' => [
                'id' => 'SP_S1',
                'name' => 'Sister-in-law (Spouse of S1)',
                'parents' => [],
                'children' => ['N1'],
                'siblings' => [],
                'spouses' => ['S1'],
            ],
            'N1' => [
                'id' => 'N1',
                'name' => 'Nephew (Child of S1)',
                'parents' => ['S1'],
                'children' => [],
                'siblings' => [],
            ],
            'C1' => [
                'id' => 'C1',
                'name' => 'Child',
                'parents' => ['I1'],
                'children' => [],
                'siblings' => ['S_C1'],
            ],
            'S_C1' => [
                'id' => 'S_C1',
                'name' => 'Child 2',
                'parents' => ['I1'],
                'children' => [],
                'siblings' => ['C1'],
            ],
            'S_P1' => [
                'id' => 'S_P1',
                'name' => 'Uncle',
                'parents' => [],
                'children' => [],
                'siblings' => ['P1'],
            ],
            'U1' => [
                'id' => 'U1',
                'name' => 'Unrelated Person',
                'parents' => [],
                'children' => [],
                'siblings' => [],
            ],
        ];

        $normalUser = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => true,
            'start_person_id' => 'I1',
        ]);

        $allowedIds = $service->getAllowedPersonIds($normalUser, $mockData);

        $this->assertNotNull($allowedIds);
        $this->assertContains('I1', $allowedIds);   // Start Person
        $this->assertContains('SP_I1', $allowedIds); // Spouse of Start Person
        $this->assertContains('P1', $allowedIds);   // Ancestor (Father)
        $this->assertContains('P2', $allowedIds);   // Ancestor (Mother)
        $this->assertContains('C1', $allowedIds);   // Descendant (Child)
        $this->assertContains('S1', $allowedIds);   // Sibling of Start Person
        $this->assertContains('SP_S1', $allowedIds); // Spouse of Sibling (Sister-in-law)
        $this->assertContains('N1', $allowedIds);   // Descendant of Start Person's sibling (Nephew)
        $this->assertContains('S_P1', $allowedIds); // Sibling of Ancestor (Uncle)
        $this->assertContains('S_C1', $allowedIds); // Sibling of Descendant
        $this->assertNotContains('U1', $allowedIds); // Unrelated Person must be hidden
    }

    public function test_superuser_has_unrestricted_access(): void
    {
        $service = new LineagePermissionService();

        $superuser = User::factory()->create([
            'is_superuser' => true,
            'is_verified' => true,
            'start_person_id' => 'I1',
        ]);

        $mockData = ['I1' => ['id' => 'I1']];
        $allowedIds = $service->getAllowedPersonIds($superuser, $mockData);

        $this->assertNull($allowedIds); // null indicates unrestricted
    }

    public function test_superuser_can_assign_start_person_to_user(): void
    {
        $superuser = User::factory()->create([
            'is_superuser' => true,
            'is_verified' => true,
        ]);

        $verifiedUser = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => true,
        ]);

        $response = $this->actingAs($superuser)->patch(route('admin.users.start-person', $verifiedUser), [
            'start_person_id' => 'I1',
        ]);

        $response->assertRedirect();
        $this->assertEquals('I1', $verifiedUser->fresh()->start_person_id);
    }

    public function test_lineage_permission_service_includes_spouse_ancestors_descendants_siblings_and_their_descendants(): void
    {
        $service = new LineagePermissionService();

        $mockData = [
            'I1' => [
                'id' => 'I1',
                'name' => 'Start Person',
                'parents' => [],
                'children' => [],
                'siblings' => [],
                'spouses' => ['SP1'],
            ],
            'SP1' => [
                'id' => 'SP1',
                'name' => 'Spouse',
                'parents' => ['SP1_P1'],
                'children' => ['SP1_C1'],
                'siblings' => ['SP1_S1'],
                'spouses' => ['I1'],
            ],
            'SP1_P1' => [
                'id' => 'SP1_P1',
                'name' => 'Father-in-law (Spouse Ancestor)',
                'parents' => [],
                'children' => ['SP1', 'SP1_S1'],
                'siblings' => [],
            ],
            'SP1_C1' => [
                'id' => 'SP1_C1',
                'name' => 'Step-child (Spouse Descendant)',
                'parents' => ['SP1'],
                'children' => [],
                'siblings' => [],
            ],
            'SP1_S1' => [
                'id' => 'SP1_S1',
                'name' => 'Brother-in-law (Spouse Sibling)',
                'parents' => ['SP1_P1'],
                'children' => ['SP1_N1'],
                'siblings' => ['SP1'],
                'spouses' => ['SP1_S1_SP'],
            ],
            'SP1_S1_SP' => [
                'id' => 'SP1_S1_SP',
                'name' => 'Spouse of Spouse Sibling',
                'parents' => [],
                'children' => ['SP1_N1'],
                'siblings' => [],
                'spouses' => ['SP1_S1'],
            ],
            'SP1_N1' => [
                'id' => 'SP1_N1',
                'name' => 'Spouse Nephew (Descendant of Spouse Sibling)',
                'parents' => ['SP1_S1'],
                'children' => [],
                'siblings' => [],
                'spouses' => ['SP1_N1_SP'],
            ],
            'SP1_N1_SP' => [
                'id' => 'SP1_N1_SP',
                'name' => 'Spouse of Nephew (Spouse of Descendant of Sibling)',
                'parents' => [],
                'children' => [],
                'siblings' => [],
                'spouses' => ['SP1_N1'],
            ],
            'U1' => [
                'id' => 'U1',
                'name' => 'Unrelated',
                'parents' => [],
                'children' => [],
                'siblings' => [],
            ],
        ];

        $user = User::factory()->create([
            'is_superuser' => false,
            'is_verified' => true,
            'start_person_id' => 'I1',
        ]);

        $allowedIds = $service->getAllowedPersonIds($user, $mockData);

        $this->assertNotNull($allowedIds);
        $this->assertContains('I1', $allowedIds);         // Start Person
        $this->assertContains('SP1', $allowedIds);        // Spouse
        $this->assertContains('SP1_P1', $allowedIds);     // Spouse Ancestor
        $this->assertContains('SP1_C1', $allowedIds);     // Spouse Descendant
        $this->assertContains('SP1_S1', $allowedIds);     // Spouse Sibling
        $this->assertContains('SP1_S1_SP', $allowedIds);  // Spouse of Spouse Sibling
        $this->assertContains('SP1_N1', $allowedIds);     // Spouse Sibling's Descendant
        $this->assertContains('SP1_N1_SP', $allowedIds);  // Spouse of Sibling's Descendant
        $this->assertNotContains('U1', $allowedIds);       // Unrelated Person
    }
}


