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

        // 1. Direct Ancestors (parents, grandparents, etc.)
        $queue = [$cleanStartId];
        $visitedAncestors = [$cleanStartId => true];

        while (!empty($queue)) {
            $currId = array_shift($queue);
            if (!isset($allIndividuals[$currId])) {
                continue;
            }

            $parents = $allIndividuals[$currId]['parents'] ?? [];
            foreach ($parents as $pId) {
                if (!isset($visitedAncestors[$pId])) {
                    $visitedAncestors[$pId] = true;
                    $allowed[$pId] = true;
                    $queue[] = $pId;
                }
            }
        }

        // 2. Direct Descendants (children, grandchildren, etc.)
        $queue = [$cleanStartId];
        $visitedDescendants = [$cleanStartId => true];

        while (!empty($queue)) {
            $currId = array_shift($queue);
            if (!isset($allIndividuals[$currId])) {
                continue;
            }

            $children = $allIndividuals[$currId]['children'] ?? [];
            foreach ($children as $cId) {
                if (!isset($visitedDescendants[$cId])) {
                    $visitedDescendants[$cId] = true;
                    $allowed[$cId] = true;
                    $queue[] = $cId;
                }
            }
        }

        // 3. Direct Lineage = {startPersonId} ∪ Ancestors ∪ Descendants
        $directLineage = array_keys($allowed);

        // 4. Collateral Lineage: All siblings of every person in Direct Lineage
        foreach ($directLineage as $personId) {
            if (!isset($allIndividuals[$personId])) {
                continue;
            }

            $siblings = $allIndividuals[$personId]['siblings'] ?? [];
            foreach ($siblings as $sibId) {
                $allowed[$sibId] = true;
            }
        }

        // 5. Descendants of Start Person's own siblings (nieces, nephews, grand-nieces/nephews)
        $startPersonSiblings = $allIndividuals[$cleanStartId]['siblings'] ?? [];
        $sibQueue = $startPersonSiblings;
        $visitedSibDescendants = array_flip($startPersonSiblings);

        while (!empty($sibQueue)) {
            $currId = array_shift($sibQueue);
            if (!isset($allIndividuals[$currId])) {
                continue;
            }

            $children = $allIndividuals[$currId]['children'] ?? [];
            foreach ($children as $cId) {
                if (!isset($visitedSibDescendants[$cId])) {
                    $visitedSibDescendants[$cId] = true;
                    $allowed[$cId] = true;
                    $sibQueue[] = $cId;
                }
            }
        }

        // 6. Spouses: Spouses and co-parents of every person in the allowed set
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
}
