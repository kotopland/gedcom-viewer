<?php

namespace App\Services;

use App\Models\User;

class LineagePermissionService
{
    /**
     * Get the array of allowed person IDs for the user based on their start_person_id.
     * Returns null if the user has unrestricted access (e.g. Superuser or no start_person_id set).
     *
     * @param User|null $user
     * @param array<string, array> $allIndividuals
     * @return array<string>|null
     */
    public function getAllowedPersonIds(?User $user, array $allIndividuals): ?array
    {
        if (!$user || $user->isSuperuser()) {
            return null; // Unlimited access for superusers
        }

        $startPersonId = trim((string) $user->start_person_id);
        if ($startPersonId === '') {
            return null; // Unlimited access if no specific start person is configured
        }

        $cleanStartId = trim($startPersonId, '@');
        if (!isset($allIndividuals[$cleanStartId])) {
            // Case-insensitive ID lookup fallback
            foreach (array_keys($allIndividuals) as $id) {
                if (strcasecmp($id, $cleanStartId) === 0) {
                    $cleanStartId = $id;
                    break;
                }
            }
        }

        if (!isset($allIndividuals[$cleanStartId])) {
            // Name lookup fallback
            foreach ($allIndividuals as $id => $ind) {
                if (strcasecmp($ind['name'] ?? '', $startPersonId) === 0) {
                    $cleanStartId = $id;
                    break;
                }
            }
        }

        if (!isset($allIndividuals[$cleanStartId])) {
            return null;
        }

        $allowed = [];
        $allowed[$cleanStartId] = true;

        // 1. Direct Ancestors (parents, grandparents, etc.) of Start Person
        $this->collectAncestors($cleanStartId, $allIndividuals, $allowed);

        // 2. Direct Descendants (children, grandchildren, etc.) of Start Person
        $this->collectDescendants($cleanStartId, $allIndividuals, $allowed);

        // 3. Direct Lineage = {startPersonId} ∪ Ancestors ∪ Descendants
        $directLineage = array_keys($allowed);

        // 4. Siblings & Sibling Descendants of everyone in Direct Lineage
        foreach ($directLineage as $personId) {
            $this->collectSiblingsAndDescendants($personId, $allIndividuals, $allowed);
        }

        // 5. Spouse's Lineage: Spouses of Start Person, their ancestors, descendants, siblings, and sibling descendants
        $startPersonSpouseIds = $allIndividuals[$cleanStartId]['spouses'] ?? [];
        $startPersonChildren = $allIndividuals[$cleanStartId]['children'] ?? [];

        foreach ($startPersonChildren as $cId) {
            if (isset($allIndividuals[$cId])) {
                foreach ($allIndividuals[$cId]['parents'] ?? [] as $coParentId) {
                    if ($coParentId !== $cleanStartId) {
                        $startPersonSpouseIds[] = $coParentId;
                    }
                }
            }
        }
        $startPersonSpouseIds = array_unique($startPersonSpouseIds);

        foreach ($startPersonSpouseIds as $spouseId) {
            if (!isset($allIndividuals[$spouseId])) {
                continue;
            }

            $allowed[$spouseId] = true;

            // Spouse's Ancestors
            $this->collectAncestors($spouseId, $allIndividuals, $allowed);

            // Spouse's Descendants
            $this->collectDescendants($spouseId, $allIndividuals, $allowed);

            // Spouse's Siblings & Sibling Descendants
            $this->collectSiblingsAndDescendants($spouseId, $allIndividuals, $allowed);
        }

        // 6. Spouses & Co-parents of EVERY person in the allowed set
        $visibleSet = array_keys($allowed);
        foreach ($visibleSet as $personId) {
            if (!isset($allIndividuals[$personId])) {
                continue;
            }

            // Direct spouses list
            $spouses = $allIndividuals[$personId]['spouses'] ?? [];
            foreach ($spouses as $spId) {
                $allowed[$spId] = true;
            }

            // Co-parents via children
            $children = $allIndividuals[$personId]['children'] ?? [];
            foreach ($children as $cId) {
                if (isset($allIndividuals[$cId])) {
                    foreach ($allIndividuals[$cId]['parents'] ?? [] as $coParentId) {
                        if ($coParentId !== $personId) {
                            $allowed[$coParentId] = true;
                        }
                    }
                }
            }
        }

        return array_keys($allowed);
    }

    /**
     * Traverse up the tree to collect all ancestors.
     */
    private function collectAncestors(string $startId, array $allIndividuals, array &$allowed): void
    {
        $queue = [$startId];
        $visited = [$startId => true];

        while (!empty($queue)) {
            $currId = array_shift($queue);
            if (!isset($allIndividuals[$currId])) {
                continue;
            }

            $parents = $allIndividuals[$currId]['parents'] ?? [];
            foreach ($parents as $pId) {
                if (!isset($visited[$pId])) {
                    $visited[$pId] = true;
                    $allowed[$pId] = true;
                    $queue[] = $pId;
                }
            }
        }
    }

    /**
     * Traverse down the tree to collect all descendants.
     */
    private function collectDescendants(string $startId, array $allIndividuals, array &$allowed): void
    {
        $queue = [$startId];
        $visited = [$startId => true];

        while (!empty($queue)) {
            $currId = array_shift($queue);
            if (!isset($allIndividuals[$currId])) {
                continue;
            }

            $children = $allIndividuals[$currId]['children'] ?? [];
            foreach ($children as $cId) {
                if (!isset($visited[$cId])) {
                    $visited[$cId] = true;
                    $allowed[$cId] = true;
                    $queue[] = $cId;
                }
            }
        }
    }

    /**
     * Collect all siblings of a person, their spouses, and all of their descendants.
     */
    private function collectSiblingsAndDescendants(string $personId, array $allIndividuals, array &$allowed): void
    {
        if (!isset($allIndividuals[$personId])) {
            return;
        }

        $siblings = $allIndividuals[$personId]['siblings'] ?? [];
        foreach ($siblings as $sibId) {
            $allowed[$sibId] = true;
            $this->collectDescendants($sibId, $allIndividuals, $allowed);
        }
    }
}


