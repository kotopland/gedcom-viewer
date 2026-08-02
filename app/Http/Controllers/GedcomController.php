<?php

namespace App\Http\Controllers;

use App\Services\GedcomParserService;
use App\Services\LineagePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GedcomController extends Controller
{
    public function index(Request $request, GedcomParserService $parser, LineagePermissionService $lineageService): InertiaResponse
    {
        $data = $parser->getOrParseData();
        $user = $request->user();

        $rootPersonId = null;
        $hasEnvDefault = false;

        // Check if user has an assigned start_person_id
        if ($user && !$user->isSuperuser() && !empty($user->start_person_id)) {
            $cleanUserStartId = trim($user->start_person_id, '@');
            if (isset($data['individuals'][$cleanUserStartId])) {
                $rootPersonId = $cleanUserStartId;
                $hasEnvDefault = true;
            }
        }

        $envPerson = trim((string) (
            env('GEDCOM_START_PERSON') ?:
            env('GEDCOM_ROOT_PERSON') ?:
            env('GEDCOM_DEFAULT_PERSON') ?:
            env('GEDCOM_DEFAULT_PERSON_ID') ?:
            env('GEDCOM_DEFAULT_PERSON_NAME') ?:
            env('GEDCOM_PERSON_ID') ?:
            env('GEDCOM_PERSON_NAME') ?:
            env('GEDCOM_PERSON') ?: ''
        ));

        if ($rootPersonId === null && $envPerson !== '') {
            $cleanEnvId = trim($envPerson, '@');
            if (isset($data['individuals'][$cleanEnvId])) {
                $rootPersonId = $cleanEnvId;
                $hasEnvDefault = true;
            } else {
                // Try searching by name (exact match first, then partial match)
                $envLower = strtolower($envPerson);
                $partialMatchId = null;

                foreach ($data['individuals'] as $id => $ind) {
                    $indNameLower = strtolower($ind['name'] ?? '');
                    if ($indNameLower === $envLower) {
                        $rootPersonId = $id;
                        $hasEnvDefault = true;
                        break;
                    }
                    if ($partialMatchId === null && str_contains($indNameLower, $envLower)) {
                        $partialMatchId = $id;
                    }
                }

                if (!$rootPersonId && $partialMatchId !== null) {
                    $rootPersonId = $partialMatchId;
                    $hasEnvDefault = true;
                }
            }
        }

        // Fallback if no env variable or match found
        if ($rootPersonId === null) {
            foreach ($data['individuals'] as $id => $ind) {
                if ($rootPersonId === null) {
                    $rootPersonId = $id;
                }
                if ($ind['primary_media'] !== null) {
                    $rootPersonId = $id;
                    break;
                }
            }
        }

        return Inertia::render('Gedcom/Index', [
            'stats' => $data['stats'],
            'rootPersonId' => $rootPersonId,
            'defaultTab' => 'tree',
        ]);
    }

    public function reimport(GedcomParserService $parser)
    {
        $data = $parser->parseAndCache(true);

        return response()->json([
            'message' => 'GEDCOM archive re-imported and media refreshed successfully.',
            'stats' => $data['stats'],
        ]);
    }


    public function search(Request $request, GedcomParserService $parser, LineagePermissionService $lineageService)
    {
        $data = $parser->getOrParseData($request->boolean('refresh'));

        $allowedIds = $lineageService->getAllowedPersonIds($request->user(), $data['individuals']);
        $allowedMap = $allowedIds !== null ? array_flip($allowedIds) : null;

        $q = strtolower(trim($request->input('q', '')));
        $surnameFilter = strtolower(trim($request->input('surname', '')));
        $gender = strtoupper(trim($request->input('gender', '')));
        $hasMedia = $request->boolean('has_media');
        $minYear = $request->filled('min_year') ? (int) $request->input('min_year') : null;
        $maxYear = $request->filled('max_year') ? (int) $request->input('max_year') : null;
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(10, (int) $request->input('limit', 24)));

        $filtered = [];
        foreach ($data['individuals'] as $ind) {
            if ($allowedMap !== null && !isset($allowedMap[$ind['id']])) {
                continue;
            }
            if ($q !== '') {
                $nameMatch = str_contains(strtolower($ind['name']), $q);
                $placeMatch = str_contains(strtolower($ind['birth_place']), $q) || str_contains(strtolower($ind['death_place']), $q);
                if (!$nameMatch && !$placeMatch) {
                    $altMatch = false;
                    foreach ($ind['all_names'] ?? [] as $an) {
                        if (str_contains(strtolower($an['name']), $q)) {
                            $altMatch = true;
                            break;
                        }
                    }
                    if (!$altMatch) {
                        continue;
                    }
                }
            }

            if ($surnameFilter !== '') {
                $surnameMatch = strtolower($ind['surname']) === $surnameFilter;
                if (!$surnameMatch) {
                    foreach ($ind['all_names'] ?? [] as $an) {
                        if (strtolower($an['surname']) === $surnameFilter) {
                            $surnameMatch = true;
                            break;
                        }
                    }
                }
                if (!$surnameMatch) {
                    continue;
                }
            }

            if ($gender !== '' && $ind['sex'] !== $gender) {
                continue;
            }

            if ($hasMedia && empty($ind['media_items'])) {
                continue;
            }

            if ($minYear !== null && ($ind['birth_year'] === null || $ind['birth_year'] < $minYear)) {
                continue;
            }

            if ($maxYear !== null && ($ind['birth_year'] === null || $ind['birth_year'] > $maxYear)) {
                continue;
            }

            $filtered[] = [
                'id' => $ind['id'],
                'name' => $ind['name'],
                'given_name' => $ind['given_name'],
                'surname' => $ind['surname'],
                'sex' => $ind['sex'],
                'birth_date' => $ind['birth_date'],
                'birth_place' => $ind['birth_place'],
                'birth_year' => $ind['birth_year'],
                'death_date' => $ind['death_date'],
                'death_year' => $ind['death_year'],
                'primary_media' => $ind['primary_media'],
                'media_count' => count($ind['media_items']),
            ];
        }

        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $limit, $limit);

        return response()->json([
            'data' => $slice,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'last_page' => max(1, (int) ceil($total / $limit)),
            ],
        ]);
    }

    public function person(Request $request, string $id, GedcomParserService $parser, LineagePermissionService $lineageService)
    {
        $data = $parser->getOrParseData();

        $allowedIds = $lineageService->getAllowedPersonIds($request->user(), $data['individuals']);
        $allowedMap = $allowedIds !== null ? array_flip($allowedIds) : null;

        if (!isset($data['individuals'][$id]) || ($allowedMap !== null && !isset($allowedMap[$id]))) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $ind = $data['individuals'][$id];

        // Format compact relation helper
        $formatMini = function (string $relId) use ($data, $allowedMap) {
            if ($allowedMap !== null && !isset($allowedMap[$relId])) {
                return null;
            }
            if (!isset($data['individuals'][$relId])) {
                return ['id' => $relId, 'name' => 'Unknown'];
            }
            $r = $data['individuals'][$relId];
            return [
                'id' => $r['id'],
                'name' => $r['name'],
                'sex' => $r['sex'],
                'birth_year' => $r['birth_year'],
                'death_year' => $r['death_year'],
                'primary_media' => $r['primary_media'],
            ];
        };

        $filterNull = fn (array $arr) => array_values(array_filter(array_map($formatMini, $arr), fn ($item) => $item !== null));

        $parents = $filterNull($ind['parents']);
        $spouses = $filterNull($ind['spouses']);
        $children = $filterNull($ind['children']);
        $siblings = $filterNull($ind['siblings']);

        $tagLabels = [
            'BIRT' => 'Birth',
            'CHR'  => 'Christening',
            'BAPT' => 'Baptism',
            'CONF' => 'Confirmation',
            'FCOM' => 'First Communion',
            'BARM' => 'Bar Mitzvah',
            'BASM' => 'Bat Mitzvah',
            'ADOP' => 'Adoption',
            'GRAD' => 'Graduation',
            'RETI' => 'Retirement',
            'DEAT' => 'Death',
            'BURI' => 'Burial',
            'CREM' => 'Cremation',
            'EMIG' => 'Emigration',
            'IMMI' => 'Immigration',
            'NATU' => 'Naturalization',
            'CENS' => 'Census',
            'PROB' => 'Probate',
            'WILL' => 'Will',
            'OCCU' => 'Occupation',
            'RESI' => 'Residence',
            'EDUC' => 'Education',
            'DSCR' => 'Physical Description',
            'RELG' => 'Religion',
            'TITL' => 'Title',
            'FACT' => 'Fact',
            'EVEN' => 'Event',
            'MARR' => 'Marriage',
            'DIV'  => 'Divorce',
            'ENG'  => 'Engagement',
            'ANUL' => 'Annulment',
            'MARS' => 'Marriage Settlement',
            'MARB' => 'Marriage Banns',
            'MARC' => 'Marriage Contract',
            'MARL' => 'Marriage License',
            'ORDN' => 'Ordination',
        ];

        $rawEvents = $ind['events'] ?? [];
        $timelineEvents = [];
        $seenKeys = [];

        // Add direct individual events
        foreach ($rawEvents as $idx => $ev) {
            $tag = $ev['tag'] ?? 'EVEN';
            $date = $ev['date'] ?? '';
            $place = $ev['place'] ?? '';
            $val = $ev['value'] ?? '';
            $type = $ev['type'] ?? '';
            $key = "ind_{$tag}_{$date}_{$place}_{$val}";

            if (isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;

            $title = !empty($type) ? $type : ($tagLabels[$tag] ?? $tag);

            $timelineEvents[] = [
                'id' => "ev_ind_{$idx}",
                'tag' => $tag,
                'title' => $title,
                'date' => $date,
                'place' => $place,
                'year' => $ev['year'] ?? null,
                'value' => $val,
                'note' => $ev['note'] ?? '',
                'age' => $ev['age'] ?? '',
                'cause' => $ev['cause'] ?? '',
                'spouse' => null,
            ];
        }

        // Add birth event if not present in events array
        if (!empty($ind['birth_date']) || !empty($ind['birth_place'])) {
            $hasBirth = false;
            foreach ($timelineEvents as $te) {
                if ($te['tag'] === 'BIRT') {
                    $hasBirth = true;
                    break;
                }
            }
            if (!$hasBirth) {
                $bYear = $ind['birth_year'] ?? null;
                $timelineEvents[] = [
                    'id' => 'ev_birt_fallback',
                    'tag' => 'BIRT',
                    'title' => 'Birth',
                    'date' => $ind['birth_date'] ?? '',
                    'place' => $ind['birth_place'] ?? '',
                    'year' => $bYear,
                    'value' => '',
                    'note' => '',
                    'age' => '',
                    'cause' => '',
                    'spouse' => null,
                ];
            }
        }

        // Add death event if not present in events array
        if (!empty($ind['death_date']) || !empty($ind['death_place'])) {
            $hasDeath = false;
            foreach ($timelineEvents as $te) {
                if ($te['tag'] === 'DEAT') {
                    $hasDeath = true;
                    break;
                }
            }
            if (!$hasDeath) {
                $dYear = $ind['death_year'] ?? null;
                $timelineEvents[] = [
                    'id' => 'ev_deat_fallback',
                    'tag' => 'DEAT',
                    'title' => 'Death',
                    'date' => $ind['death_date'] ?? '',
                    'place' => $ind['death_place'] ?? '',
                    'year' => $dYear,
                    'value' => '',
                    'note' => '',
                    'age' => '',
                    'cause' => '',
                    'spouse' => null,
                ];
            }
        }

        // Add burial event if not present in events array
        if (!empty($ind['burial_date']) || !empty($ind['burial_place'])) {
            $hasBurial = false;
            foreach ($timelineEvents as $te) {
                if ($te['tag'] === 'BURI') {
                    $hasBurial = true;
                    break;
                }
            }
            if (!$hasBurial) {
                $timelineEvents[] = [
                    'id' => 'ev_buri_fallback',
                    'tag' => 'BURI',
                    'title' => 'Burial',
                    'date' => $ind['burial_date'] ?? '',
                    'place' => $ind['burial_place'] ?? '',
                    'year' => null,
                    'value' => '',
                    'note' => '',
                    'age' => '',
                    'cause' => '',
                    'spouse' => null,
                ];
            }
        }

        // Add family events (Marriage, Divorce, etc.)
        foreach ($ind['fams'] ?? [] as $fIdx => $famId) {
            if (!isset($data['families'][$famId])) {
                continue;
            }
            $fam = $data['families'][$famId];
            $spouseId = null;
            if ($ind['id'] === ($fam['husband_id'] ?? '')) {
                $spouseId = $fam['wife_id'] ?? null;
            } elseif ($ind['id'] === ($fam['wife_id'] ?? '')) {
                $spouseId = $fam['husband_id'] ?? null;
            }
            $spouseInfo = $spouseId ? $formatMini($spouseId) : null;

            $famEvs = $fam['events'] ?? [];
            if (!empty($famEvs)) {
                foreach ($famEvs as $feIdx => $fev) {
                    $tag = $fev['tag'] ?? 'MARR';
                    $date = $fev['date'] ?? '';
                    $place = $fev['place'] ?? '';
                    $val = $fev['value'] ?? '';
                    $type = $fev['type'] ?? '';
                    $key = "fam_{$famId}_{$tag}_{$date}_{$place}";

                    if (isset($seenKeys[$key])) {
                        continue;
                    }
                    $seenKeys[$key] = true;

                    $title = !empty($type) ? $type : ($tagLabels[$tag] ?? $tag);

                    $timelineEvents[] = [
                        'id' => "ev_fam_{$famId}_{$feIdx}",
                        'tag' => $tag,
                        'title' => $title,
                        'date' => $date,
                        'place' => $place,
                        'year' => $fev['year'] ?? null,
                        'value' => $val,
                        'note' => $fev['note'] ?? '',
                        'age' => $fev['age'] ?? '',
                        'cause' => $fev['cause'] ?? '',
                        'spouse' => $spouseInfo,
                    ];
                }
            } elseif (!empty($fam['marriage_date']) || !empty($fam['marriage_place'])) {
                $mYear = null;
                if (preg_match('/\b(1\d{3}|20\d{2})\b/', $fam['marriage_date'] ?? '', $ym)) {
                    $mYear = (int) $ym[1];
                }
                $key = "fam_{$famId}_MARR_{$fam['marriage_date']}_{$fam['marriage_place']}";
                if (!isset($seenKeys[$key])) {
                    $seenKeys[$key] = true;
                    $timelineEvents[] = [
                        'id' => "ev_fam_marr_{$famId}",
                        'tag' => 'MARR',
                        'title' => 'Marriage',
                        'date' => $fam['marriage_date'] ?? '',
                        'place' => $fam['marriage_place'] ?? '',
                        'year' => $mYear,
                        'value' => '',
                        'note' => '',
                        'age' => '',
                        'cause' => '',
                        'spouse' => $spouseInfo,
                    ];
                }
            }
        }

        // Sort events chronologically
        usort($timelineEvents, function ($a, $b) {
            $tagWeights = [
                'BIRT' => 1,
                'CHR'  => 2,
                'BAPT' => 3,
                'CONF' => 4,
                'DEAT' => 98,
                'BURI' => 99,
                'CREM' => 100,
            ];

            $aYear = $a['year'];
            $bYear = $b['year'];

            if ($aYear !== null && $bYear !== null) {
                if ($aYear !== $bYear) {
                    return $aYear <=> $bYear;
                }
            } elseif ($aYear !== null && $bYear === null) {
                if ($b['tag'] === 'BIRT') return 1;
                if ($b['tag'] === 'DEAT' || $b['tag'] === 'BURI') return -1;
            } elseif ($aYear === null && $bYear !== null) {
                if ($a['tag'] === 'BIRT') return -1;
                if ($a['tag'] === 'DEAT' || $a['tag'] === 'BURI') return 1;
            }

            $aWeight = $tagWeights[$a['tag']] ?? 50;
            $bWeight = $tagWeights[$b['tag']] ?? 50;

            return $aWeight <=> $bWeight;
        });

        $personData = array_merge($ind, [
            'events' => $timelineEvents,
        ]);

        return response()->json([
            'person' => $personData,
            'relations' => [
                'parents' => $parents,
                'spouses' => $spouses,
                'children' => $children,
                'siblings' => $siblings,
            ],
        ]);
    }

    public function tree(Request $request, string $id, GedcomParserService $parser, LineagePermissionService $lineageService)
    {
        $data = $parser->getOrParseData();

        $allowedIds = $lineageService->getAllowedPersonIds($request->user(), $data['individuals']);
        $allowedMap = $allowedIds !== null ? array_flip($allowedIds) : null;

        if (!isset($data['individuals'][$id]) || ($allowedMap !== null && !isset($allowedMap[$id]))) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $ancestorLevels = max(0, min(6, (int) $request->input('ancestors', $request->input('ancestor_levels', 2))));
        $descendantLevels = max(0, min(6, (int) $request->input('descendants', $request->input('descendant_levels', 2))));

        $ancestorMaxDepth = $ancestorLevels + 1;
        $descendantMaxDepth = $descendantLevels + 1;

        $getMarriageInfo = function (string $personId) use ($data) {
            if (!isset($data['individuals'][$personId])) return ['date' => null, 'year' => null];
            $ind = $data['individuals'][$personId];
            foreach ($ind['fams'] ?? [] as $famId) {
                if (isset($data['families'][$famId])) {
                    $fam = $data['families'][$famId];
                    if (!empty($fam['marriage_date'])) {
                        $year = null;
                        if (preg_match('/\b(1\d{3}|20\d{2})\b/', $fam['marriage_date'], $m)) {
                            $year = (int) $m[1];
                        }
                        return ['date' => $fam['marriage_date'], 'year' => $year];
                    }
                    foreach ($fam['events'] ?? [] as $fev) {
                        if (($fev['tag'] ?? '') === 'MARR' && !empty($fev['date'])) {
                            return ['date' => $fev['date'], 'year' => $fev['year'] ?? null];
                        }
                    }
                }
            }
            return ['date' => null, 'year' => null];
        };

        $formatMiniSpouses = function (array $spouseIds) use ($data, $allowedMap, $getMarriageInfo) {
            $result = [];
            foreach ($spouseIds as $sId) {
                if ($allowedMap !== null && !isset($allowedMap[$sId])) continue;
                if (!isset($data['individuals'][$sId])) continue;
                $s = $data['individuals'][$sId];
                $mInfo = $getMarriageInfo($sId);
                $result[] = [
                    'id' => $s['id'],
                    'name' => $s['name'],
                    'birth_date' => $s['birth_date'] ?? null,
                    'birth_year' => $s['birth_year'],
                    'death_date' => $s['death_date'] ?? null,
                    'death_year' => $s['death_year'],
                    'marriage_date' => $mInfo['date'],
                    'marriage_year' => $mInfo['year'],
                    'primary_media' => $s['primary_media'],
                ];
            }
            return $result;
        };

        $buildAncestorTree = function (string $personId, int $depth = 0) use (&$buildAncestorTree, $data, $ancestorMaxDepth, $allowedMap, $formatMiniSpouses, $getMarriageInfo) {
            if ($depth >= $ancestorMaxDepth || !isset($data['individuals'][$personId]) || ($allowedMap !== null && !isset($allowedMap[$personId]))) {
                return null;
            }

            $ind = $data['individuals'][$personId];
            $mInfo = $getMarriageInfo($personId);

            $node = [
                'id' => $ind['id'],
                'name' => $ind['name'],
                'sex' => $ind['sex'],
                'birth_date' => $ind['birth_date'] ?? null,
                'birth_year' => $ind['birth_year'],
                'death_date' => $ind['death_date'] ?? null,
                'death_year' => $ind['death_year'],
                'marriage_date' => $mInfo['date'],
                'marriage_year' => $mInfo['year'],
                'birth_place' => $ind['birth_place'],
                'primary_media' => $ind['primary_media'],
                'spouses' => $formatMiniSpouses($ind['spouses'] ?? []),
                'parents' => [],
            ];

            foreach ($ind['parents'] as $pId) {
                $pNode = $buildAncestorTree($pId, $depth + 1);
                if ($pNode) {
                    $node['parents'][] = $pNode;
                }
            }

            return $node;
        };

        $buildDescendantTree = function (string $personId, int $depth = 0) use (&$buildDescendantTree, $data, $descendantMaxDepth, $allowedMap, $formatMiniSpouses, $getMarriageInfo) {
            if ($depth >= $descendantMaxDepth || !isset($data['individuals'][$personId]) || ($allowedMap !== null && !isset($allowedMap[$personId]))) {
                return null;
            }

            $ind = $data['individuals'][$personId];
            $mInfo = $getMarriageInfo($personId);

            $node = [
                'id' => $ind['id'],
                'name' => $ind['name'],
                'sex' => $ind['sex'],
                'birth_date' => $ind['birth_date'] ?? null,
                'birth_year' => $ind['birth_year'],
                'death_date' => $ind['death_date'] ?? null,
                'death_year' => $ind['death_year'],
                'marriage_date' => $mInfo['date'],
                'marriage_year' => $mInfo['year'],
                'primary_media' => $ind['primary_media'],
                'spouses' => $formatMiniSpouses($ind['spouses'] ?? []),
                'children' => [],
            ];

            foreach ($ind['children'] as $cId) {
                $cNode = $buildDescendantTree($cId, $depth + 1);
                if ($cNode) {
                    $node['children'][] = $cNode;
                }
            }

            return $node;
        };

        return response()->json([
            'ancestors' => $ancestorLevels > 0 ? $buildAncestorTree($id) : null,
            'descendants' => $descendantLevels > 0 ? $buildDescendantTree($id) : null,
        ]);
    }

    public function media(Request $request, GedcomParserService $parser, LineagePermissionService $lineageService)
    {
        $data = $parser->getOrParseData();

        $allowedIds = $lineageService->getAllowedPersonIds($request->user(), $data['individuals']);
        $allowedMap = $allowedIds !== null ? array_flip($allowedIds) : null;

        $type = strtolower(trim($request->input('type', 'all')));
        $q = strtolower(trim($request->input('q', '')));
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(60, max(12, (int) $request->input('limit', 24)));

        // Build person linkage map for each media object
        $objectPeopleMap = [];
        foreach ($data['individuals'] as $ind) {
            if ($allowedMap !== null && !isset($allowedMap[$ind['id']])) {
                continue;
            }
            foreach ($ind['media_ids'] as $mId) {
                $objectPeopleMap[$mId][] = [
                    'id' => $ind['id'],
                    'name' => $ind['name'],
                ];
            }
        }

        $filtered = [];
        foreach ($data['objects'] as $obj) {
            $file = strtolower($obj['file']);
            $title = strtolower($obj['title']);
            $mime = strtolower($obj['mime']);

            if ($q !== '' && !str_contains($file, $q) && !str_contains($title, $q)) {
                continue;
            }

            $isImage = str_contains($mime, 'image') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
            $isPdf = str_contains($mime, 'pdf') || preg_match('/\.pdf$/i', $file);
            $isAudio = str_contains($mime, 'audio') || preg_match('/\.(m4a|mp3|wav|ogg)$/i', $file);

            if ($type === 'photo' && !$isImage) {
                continue;
            }
            if ($type === 'document' && !$isPdf) {
                continue;
            }
            if ($type === 'audio' && !$isAudio) {
                continue;
            }

            $obj['people'] = $objectPeopleMap[$obj['id']] ?? [];
            $obj['category'] = $isImage ? 'photo' : ($isPdf ? 'document' : ($isAudio ? 'audio' : 'other'));
            $filtered[] = $obj;
        }

        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $limit, $limit);

        return response()->json([
            'data' => $slice,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'last_page' => max(1, (int) ceil($total / $limit)),
            ],
        ]);
    }

    public function serveMedia(string $filename): BinaryFileResponse
    {
        // Sanitize filename to prevent directory traversal
        $safeFilename = basename($filename);
        $path = storage_path('app/public/gedcom/media/' . $safeFilename);

        if (!File::exists($path)) {
            abort(404, 'Media file not found');
        }

        $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'm4a' => 'audio/x-m4a',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => File::mimeType($path) ?: 'application/octet-stream',
        };

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function submitContribution(Request $request, string $id, GedcomParserService $parser)
    {
        $data = $parser->getOrParseData();

        if (!isset($data['individuals'][$id])) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $ind = $data['individuals'][$id];
        $user = $request->user();

        $request->validate([
            'note' => 'nullable|string|max:3000',
            'media' => 'nullable|file|max:20480|mimes:jpg,jpeg,png,gif,webp,pdf,m4a,mp3,wav',
        ]);

        $note = trim((string) $request->input('note', ''));
        $hasFile = $request->hasFile('media');

        if (empty($note) && !$hasFile) {
            return response()->json([
                'error' => 'Please provide a note or select a file to upload.',
            ], 422);
        }

        $mediaUrl = null;
        $originalFilename = null;

        if ($hasFile) {
            $file = $request->file('media');
            $originalFilename = $file->getClientOriginalName();
            $filename = uniqid('contrib_') . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalFilename);

            $targetDir = storage_path('app/public/contributions');
            File::ensureDirectoryExists($targetDir);

            $link = public_path('storage');
            if (!File::exists($link)) {
                @symlink(storage_path('app/public'), $link);
            }

            $file->move($targetDir, $filename);
            $mediaUrl = url('/storage/contributions/' . $filename);
        }

        // Send email to superusers
        $superusers = \App\Models\User::where('is_superuser', true)->get();
        $recipients = $superusers->pluck('email')->filter()->toArray();

        if (empty($recipients)) {
            $recipients = [config('mail.from.address') ?: 'admin@topland-family.test'];
        }

        try {
            \Illuminate\Support\Facades\Mail::to($recipients)->send(
                new \App\Mail\PersonContributionSubmittedMail(
                    $user,
                    $ind,
                    $note ?: null,
                    $mediaUrl,
                    $originalFilename
                )
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send contribution email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Thank you! Your note/media file has been submitted successfully to the administrator.',
        ]);
    }

    public function serveContributionMedia(string $filename): BinaryFileResponse
    {
        $safeFilename = basename($filename);
        $path = storage_path('app/public/contributions/' . $safeFilename);

        if (!File::exists($path)) {
            abort(404, 'Contribution media file not found');
        }

        $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'm4a' => 'audio/x-m4a',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => File::mimeType($path) ?: 'application/octet-stream',
        };

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function stats(Request $request, GedcomParserService $parser, LineagePermissionService $lineageService)
    {
        $data = $parser->getOrParseData();

        // 1. Enforce strict permissions via LineagePermissionService
        $allowedIds = $lineageService->getAllowedPersonIds($request->user(), $data['individuals']);
        $allowedMap = $allowedIds !== null ? array_flip($allowedIds) : null;

        $filteredIndividuals = [];
        foreach ($data['individuals'] as $id => $ind) {
            if ($allowedMap === null || isset($allowedMap[$id])) {
                $filteredIndividuals[$id] = $ind;
            }
        }

        $filteredFamilies = [];
        foreach ($data['families'] as $famId => $fam) {
            $husbOk = isset($fam['husband_id']) && ($allowedMap === null || isset($allowedMap[$fam['husband_id']]));
            $wifeOk = isset($fam['wife_id']) && ($allowedMap === null || isset($allowedMap[$fam['wife_id']]));
            if ($husbOk || $wifeOk) {
                $filteredFamilies[$famId] = $fam;
            }
        }

        // 2. Compute Surnames Statistics
        $surnameCounts = [];
        foreach ($filteredIndividuals as $ind) {
            $surname = trim($ind['surname'] ?? '');
            if ($surname !== '' && strtolower($surname) !== 'unknown' && $surname !== '---') {
                $surnameCounts[$surname] = ($surnameCounts[$surname] ?? 0) + 1;
            }
        }
        arsort($surnameCounts);
        $topSurnames = [];
        foreach (array_slice($surnameCounts, 0, 15, true) as $s => $cnt) {
            $topSurnames[] = ['surname' => $s, 'count' => $cnt];
        }

        // 3. Compute Countries & Places Statistics
        $placeCounts = [];
        $countryCounts = [];

        foreach ($filteredIndividuals as $ind) {
            $places = array_filter([$ind['birth_place'] ?? null, $ind['death_place'] ?? null]);
            foreach ($ind['events'] ?? [] as $evt) {
                if (!empty($evt['place'])) {
                    $places[] = $evt['place'];
                }
            }

            foreach ($places as $rawPlace) {
                $place = trim($rawPlace);
                if ($place === '') continue;

                $placeCounts[$place] = ($placeCounts[$place] ?? 0) + 1;

                // Extract country/state (last component in comma-separated location)
                $parts = array_map('trim', explode(',', $place));
                $countryCandidate = end($parts);
                if (!empty($countryCandidate) && strlen($countryCandidate) >= 2) {
                    $normCountry = match(strtolower($countryCandidate)) {
                        'usa', 'united states', 'united states of america' => 'USA',
                        'uk', 'united kingdom', 'england', 'scotland', 'wales' => 'United Kingdom',
                        'no', 'norge', 'norway' => 'Norway',
                        'se', 'sverige', 'sweden' => 'Sweden',
                        'dk', 'danmark', 'denmark' => 'Denmark',
                        'de', 'deutschland', 'germany' => 'Germany',
                        'fr', 'france' => 'France',
                        default => ucfirst($countryCandidate),
                    };
                    $countryCounts[$normCountry] = ($countryCounts[$normCountry] ?? 0) + 1;
                }
            }
        }

        arsort($placeCounts);
        arsort($countryCounts);

        $topPlaces = [];
        foreach (array_slice($placeCounts, 0, 15, true) as $p => $cnt) {
            $topPlaces[] = ['place' => $p, 'count' => $cnt];
        }

        $topCountries = [];
        foreach (array_slice($countryCounts, 0, 15, true) as $c => $cnt) {
            $topCountries[] = ['country' => $c, 'count' => $cnt];
        }

        // 4. Oldest Person Statistics
        $oldestPerson = null;
        $maxLifespan = -1;

        $maleLifespans = [];
        $femaleLifespans = [];
        $allLifespans = [];

        $earliestBirthYear = null;
        $latestBirthYear = null;

        foreach ($filteredIndividuals as $ind) {
            $bYear = $ind['birth_year'] ?? null;
            $dYear = $ind['death_year'] ?? null;

            if ($bYear !== null) {
                if ($earliestBirthYear === null || $bYear < $earliestBirthYear) {
                    $earliestBirthYear = $bYear;
                }
                if ($latestBirthYear === null || $bYear > $latestBirthYear) {
                    $latestBirthYear = $bYear;
                }
            }

            if ($bYear !== null && $dYear !== null && $dYear >= $bYear && ($dYear - $bYear) <= 115) {
                $age = $dYear - $bYear;
                $allLifespans[] = $age;
                if (($ind['sex'] ?? '') === 'M') {
                    $maleLifespans[] = $age;
                } elseif (($ind['sex'] ?? '') === 'F') {
                    $femaleLifespans[] = $age;
                }

                if ($age > $maxLifespan) {
                    $maxLifespan = $age;
                    $oldestPerson = [
                        'id' => $ind['id'],
                        'name' => $ind['name'],
                        'sex' => $ind['sex'],
                        'birth_year' => $bYear,
                        'death_year' => $dYear,
                        'age' => $age,
                        'primary_media' => $ind['primary_media'] ?? null,
                    ];
                }
            }
        }

        // 5. Biggest Difference in Age of Married Couple
        $biggestAgeDiffCouple = null;
        $maxAgeDiff = -1;

        $largestFamily = null;
        $maxChildrenCount = -1;

        foreach ($filteredFamilies as $fam) {
            $hId = $fam['husband_id'] ?? null;
            $wId = $fam['wife_id'] ?? null;

            $husband = $hId && isset($filteredIndividuals[$hId]) ? $filteredIndividuals[$hId] : null;
            $wife = $wId && isset($filteredIndividuals[$wId]) ? $filteredIndividuals[$wId] : null;

            $children = $fam['children'] ?? [];
            if (count($children) > $maxChildrenCount) {
                $maxChildrenCount = count($children);
                $largestFamily = [
                    'family_id' => $fam['id'],
                    'husband_name' => $husband['name'] ?? 'Unknown',
                    'wife_name' => $wife['name'] ?? 'Unknown',
                    'children_count' => count($children),
                ];
            }

            if ($husband && $wife) {
                $hBirth = $husband['birth_year'] ?? null;
                $wBirth = $wife['birth_year'] ?? null;

                if ($hBirth !== null && $wBirth !== null) {
                    $diff = abs($hBirth - $wBirth);
                    if ($diff > $maxAgeDiff) {
                        $maxAgeDiff = $diff;
                        $olderSpouse = $hBirth < $wBirth ? 'husband' : 'wife';
                        $biggestAgeDiffCouple = [
                            'family_id' => $fam['id'],
                            'husband' => [
                                'id' => $husband['id'],
                                'name' => $husband['name'],
                                'birth_year' => $hBirth,
                                'primary_media' => $husband['primary_media'] ?? null,
                            ],
                            'wife' => [
                                'id' => $wife['id'],
                                'name' => $wife['name'],
                                'birth_year' => $wBirth,
                                'primary_media' => $wife['primary_media'] ?? null,
                            ],
                            'age_difference' => $diff,
                            'older_spouse' => $olderSpouse,
                            'marriage_date' => $fam['marriage_date'] ?? null,
                            'marriage_year' => $fam['marriage_year'] ?? null,
                        ];
                    }
                }
            }
        }

        // 6. Lifespan Averages
        $avgLifespan = !empty($allLifespans) ? round(array_sum($allLifespans) / count($allLifespans), 1) : null;
        $avgMaleLifespan = !empty($maleLifespans) ? round(array_sum($maleLifespans) / count($maleLifespans), 1) : null;
        $avgFemaleLifespan = !empty($femaleLifespans) ? round(array_sum($femaleLifespans) / count($femaleLifespans), 1) : null;

        $maleCount = 0;
        $femaleCount = 0;
        foreach ($filteredIndividuals as $ind) {
            if (($ind['sex'] ?? '') === 'M') $maleCount++;
            if (($ind['sex'] ?? '') === 'F') $femaleCount++;
        }

        return response()->json([
            'totals' => [
                'total_individuals' => count($filteredIndividuals),
                'total_families' => count($filteredFamilies),
                'males' => $maleCount,
                'females' => $femaleCount,
                'living' => count(array_filter($filteredIndividuals, fn($i) => empty($i['death_year']) && empty($i['death_date']))),
                'deceased' => count(array_filter($filteredIndividuals, fn($i) => !empty($i['death_year']) || !empty($i['death_date']))),
            ],
            'top_surnames' => $topSurnames,
            'top_places' => $topPlaces,
            'top_countries' => $topCountries,
            'oldest_person' => $oldestPerson,
            'biggest_age_difference_couple' => $biggestAgeDiffCouple,
            'largest_family' => $largestFamily,
            'lifespan_averages' => [
                'overall' => $avgLifespan,
                'male' => $avgMaleLifespan,
                'female' => $avgFemaleLifespan,
            ],
            'generations_span' => [
                'earliest_birth_year' => $earliestBirthYear,
                'latest_birth_year' => $latestBirthYear,
                'total_years_span' => ($earliestBirthYear && $latestBirthYear) ? ($latestBirthYear - $earliestBirthYear) : null,
            ],
        ]);
    }
}

