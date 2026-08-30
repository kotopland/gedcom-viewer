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
        try {
            $data = $parser->parseAndCache(true);

            return response()->json([
                'message' => 'GEDCOM archive re-imported and media refreshed successfully.',
                'stats' => $data['stats'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to re-import GEDCOM archive: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uploadGedcom(Request $request, GedcomParserService $parser)
    {
        $request->validate([
            'file' => ['required', 'file', function ($attribute, $value, $fail) {
                $ext = strtolower($value->getClientOriginalExtension());
                if (!in_array($ext, ['ged', 'gedcom', 'txt'])) {
                    $fail('The ' . $attribute . ' must be a .ged or .gedcom file.');
                }
            }],
        ]);

        $uploadedFile = $request->file('file');
        $privateDir = storage_path('app/private');

        File::ensureDirectoryExists($privateDir);

        // Remove any existing ZIP archives so GedcomParserService detects gedcom.ged as the active source
        $zipFiles = File::glob($privateDir . '/*.zip');
        foreach ($zipFiles as $zip) {
            File::delete($zip);
        }

        // Store the uploaded file as storage/app/private/gedcom.ged
        $uploadedFile->move($privateDir, 'gedcom.ged');

        // Re-parse GEDCOM while preserving existing media cache (clearMedia: false)
        $data = $parser->parseAndCache(false);

        return response()->json([
            'message' => 'GEDCOM file uploaded and parsed successfully. Existing media cache was preserved.',
            'stats' => $data['stats'],
        ]);
    }

    public function uploadFaces(Request $request, GedcomParserService $parser)
    {
        $request->validate([
            'file' => ['required', 'file', function ($attribute, $value, $fail) {
                $ext = strtolower($value->getClientOriginalExtension());
                if ($ext !== 'json') {
                    $fail('The ' . $attribute . ' must be a valid .json file.');
                }
            }],
        ]);

        $uploadedFile = $request->file('file');
        $content = file_get_contents($uploadedFile->getRealPath());

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || (!isset($decoded['by_person']) && !isset($decoded['all_tags']))) {
            return response()->json([
                'error' => 'Invalid faces.json format. Expected JSON containing "by_person" or "all_tags".',
            ], 422);
        }

        $privateDir = storage_path('app/private');
        File::ensureDirectoryExists($privateDir);
        File::put($privateDir . '/faces.json', $content);

        // Re-parse and cache data to regenerate crops while preserving media
        $data = $parser->parseAndCache(false);

        $faceCount = count($decoded['all_tags'] ?? []);
        if ($faceCount === 0 && isset($decoded['by_person'])) {
            foreach ($decoded['by_person'] as $arr) {
                $faceCount += count($arr);
            }
        }

        return response()->json([
            'message' => "Successfully imported {$faceCount} face tag(s) into the family tree.",
            'total_faces' => $faceCount,
            'stats' => $data['stats'] ?? [],
        ]);
    }

    public function downloadFaceScript()
    {
        $path = base_path('scripts/mft11_export_faces.py');
        if (!File::exists($path)) {
            abort(404, 'Script file not found');
        }

        return response()->download($path, 'mft11_export_faces.py', [
            'Content-Type' => 'text/x-python',
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
                'given_name' => $ind['given_name'] ?? '',
                'surname' => $ind['surname'] ?? '',
                'sex' => $ind['sex'] ?? 'U',
                'birth_date' => $ind['birth_date'] ?? null,
                'birth_place' => GedcomParserService::cleanPlace($ind['birth_place'] ?? null),
                'birth_year' => $ind['birth_year'] ?? null,
                'death_date' => $ind['death_date'] ?? null,
                'death_place' => GedcomParserService::cleanPlace($ind['death_place'] ?? null),
                'death_year' => $ind['death_year'] ?? null,
                'primary_media' => $ind['primary_media'] ?? null,
                'portrait_url' => $ind['portrait_url'] ?? $ind['primary_media']['portrait_url'] ?? null,
                'media_count' => count($ind['media_items'] ?? []),
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

        $targetId = $id;
        $cleanId = trim($id, '@');
        if (isset($data['individuals'][$cleanId])) {
            $targetId = $cleanId;
        } elseif (isset($data['individuals'][$id])) {
            $targetId = $id;
        }

        if (!isset($data['individuals'][$targetId]) || ($allowedMap !== null && !isset($allowedMap[$targetId]))) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $ind = $data['individuals'][$targetId];

        // Format compact relation helper
        $formatMini = function (string $relId) use ($data, $allowedMap) {
            if ($allowedMap !== null && !isset($allowedMap[$relId])) {
                return null;
            }
            if (!isset($data['individuals'][$relId])) {
                return ['id' => $relId, 'name' => 'Unknown', 'surname' => ''];
            }
            $r = $data['individuals'][$relId];
            return [
                'id' => $r['id'] ?? $relId,
                'name' => $r['name'] ?? 'Unknown',
                'surname' => $r['surname'] ?? '',
                'sex' => $r['sex'] ?? 'U',
                'birth_year' => $r['birth_year'] ?? null,
                'death_year' => $r['death_year'] ?? null,
                'primary_media' => $r['primary_media'] ?? null,
                'portrait_url' => $r['portrait_url'] ?? $r['primary_media']['portrait_url'] ?? null,
            ];
        };

        $filterNull = fn (array $arr) => array_values(array_filter(array_map($formatMini, $arr), fn ($item) => $item !== null));

        $parents = $filterNull($ind['parents']);
        $children = $filterNull($ind['children']);
        $siblings = $filterNull($ind['siblings']);

        // Enrich spouses with marriage details from shared family records
        $spouses = array_values(array_filter(array_map(function ($sId) use ($formatMini, $ind, $data) {
            $mini = $formatMini($sId);
            if (!$mini) return null;

            $marrDate = null;
            $marrYear = null;
            $relType = null;
            foreach ($ind['fams'] ?? [] as $fId) {
                if (isset($data['families'][$fId])) {
                    $f = $data['families'][$fId];
                    if (($f['husband_id'] === $ind['id'] && $f['wife_id'] === $sId) || ($f['wife_id'] === $ind['id'] && $f['husband_id'] === $sId)) {
                        $marrDate = $f['marriage_date'] ?? null;
                        $marrYear = $f['marriage_year'] ?? null;
                        $relType = $f['relationship_type'] ?? null;
                        break;
                    }
                }
            }

            $mini['marriage_date'] = $marrDate;
            $mini['marriage_year'] = $marrYear;
            $mini['relationship_type'] = $relType;

            return $mini;
        }, $ind['spouses']), fn ($item) => $item !== null));

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
            '_PRS' => 'Civil Partnership',
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

            // If this is an individual marriage event and person has family records, let the family loop handle it with full spouse details
            $isMarrEvent = in_array($tag, ['MARR', '_PRS']) || ($tag === 'EVEN' && preg_match('/\b(marriage|married|wedding|partner)\b/i', $type . ' ' . $val));
            if ($isMarrEvent && !empty($ind['fams'])) {
                continue;
            }

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

                    if ($tag === '_PRS') {
                        $title = !empty($val) ? $val : (!empty($type) ? $type : 'Civil Partnership');
                    } elseif ($tag === 'MARR' && !empty($fam['relationship_type'])) {
                        $title = $fam['relationship_type'];
                    } else {
                        $title = !empty($type) ? $type : ($tagLabels[$tag] ?? $tag);
                    }

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
            } elseif (!empty($fam['marriage_date']) || !empty($fam['marriage_place']) || !empty($fam['relationship_type'])) {
                $mYear = null;
                if (preg_match('/\b(1\d{3}|20\d{2})\b/', $fam['marriage_date'] ?? '', $ym)) {
                    $mYear = (int) $ym[1];
                }
                $relType = !empty($fam['relationship_type']) ? $fam['relationship_type'] : 'Marriage';
                $key = "fam_{$famId}_{$relType}_{$fam['marriage_date']}_{$fam['marriage_place']}";
                if (!isset($seenKeys[$key])) {
                    $seenKeys[$key] = true;
                    $timelineEvents[] = [
                        'id' => "ev_fam_marr_{$famId}",
                        'tag' => !empty($fam['relationship_type']) ? '_PRS' : 'MARR',
                        'title' => $relType,
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
            } elseif ($spouseInfo) {
                $relType = !empty($fam['relationship_type']) ? $fam['relationship_type'] : 'Marriage';
                $key = "fam_{$famId}_{$relType}_undated";
                if (!isset($seenKeys[$key])) {
                    $seenKeys[$key] = true;
                    $timelineEvents[] = [
                        'id' => "ev_fam_marr_{$famId}",
                        'tag' => !empty($fam['relationship_type']) ? '_PRS' : 'MARR',
                        'title' => $relType,
                        'date' => '',
                        'place' => '',
                        'year' => null,
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

        $ind['birth_place'] = GedcomParserService::cleanPlace($ind['birth_place'] ?? null);
        $ind['death_place'] = GedcomParserService::cleanPlace($ind['death_place'] ?? null);
        $ind['burial_place'] = GedcomParserService::cleanPlace($ind['burial_place'] ?? null);

        foreach ($timelineEvents as &$te) {
            if (!empty($te['place'])) {
                $te['place'] = GedcomParserService::cleanPlace($te['place']);
            }
        }
        unset($te);

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

        $ancestorLevels = max(0, min(12, (int) $request->input('ancestors', $request->input('ancestor_levels', 2))));
        $descendantLevels = max(0, min(12, (int) $request->input('descendants', $request->input('descendant_levels', 2))));

        $ancestorMaxDepth = $ancestorLevels + 1;
        $descendantMaxDepth = $descendantLevels + 1;

        $getMarriageInfo = function (string $personId) use ($data) {
            if (!isset($data['individuals'][$personId])) return ['date' => null, 'year' => null, 'place' => null, 'spouse_name' => null, 'type' => null];
            $ind = $data['individuals'][$personId];
            foreach ($ind['fams'] ?? [] as $famId) {
                if (isset($data['families'][$famId])) {
                    $fam = $data['families'][$famId];
                    $spouseId = ($fam['husband_id'] ?? null) === $personId ? ($fam['wife_id'] ?? null) : ($fam['husband_id'] ?? null);
                    $spouseName = ($spouseId && isset($data['individuals'][$spouseId])) ? $data['individuals'][$spouseId]['name'] : null;

                    $date = $fam['marriage_date'] ?? null;
                    $place = GedcomParserService::cleanPlace($fam['marriage_place'] ?? null);
                    $year = null;
                    $type = !empty($fam['relationship_type']) ? $fam['relationship_type'] : null;

                    if ($date) {
                        if (preg_match('/\b(1\d{3}|20\d{2})\b/', $date, $m)) {
                            $year = (int) $m[1];
                        }
                    }

                    if (!$date) {
                        foreach ($fam['events'] ?? [] as $fev) {
                            if ((($fev['tag'] ?? '') === 'MARR' || ($fev['tag'] ?? '') === '_PRS') && !empty($fev['date'])) {
                                $date = $fev['date'];
                                $year = $fev['year'] ?? null;
                                $place = GedcomParserService::cleanPlace($fev['place'] ?? $place);
                                if (!empty($fev['type'])) {
                                    $type = $fev['type'];
                                } elseif (!empty($fev['value'])) {
                                    $type = $fev['value'];
                                }
                                break;
                            }
                        }
                    }

                    if ($date || $place || $spouseName || $type) {
                        return [
                            'date' => $date,
                            'year' => $year,
                            'place' => $place,
                            'spouse_name' => $spouseName,
                            'type' => $type ?: 'Marriage',
                        ];
                    }
                }
            }
            return ['date' => null, 'year' => null, 'place' => null, 'spouse_name' => null, 'type' => null];
        };

        $formatPersonData = function (array $ind) use ($getMarriageInfo) {
            $mInfo = $getMarriageInfo($ind['id']);

            $occupations = [];
            foreach ($ind['events'] ?? [] as $ev) {
                if (($ev['tag'] ?? '') === 'OCCU') {
                    $occu = trim($ev['value'] ?? '');
                    $place = GedcomParserService::cleanPlace($ev['place'] ?? '');
                    if ($occu === '' && !empty($ev['type'])) {
                        $occu = trim($ev['type']);
                    }
                    if ($occu !== '' && $place) {
                        if (stripos($occu, $place) === false) {
                            $occu .= " ({$place})";
                        }
                    } elseif ($occu === '' && $place) {
                        $occu = $place;
                    }
                    if ($occu !== '' && !in_array($occu, $occupations, true)) {
                        $occupations[] = $occu;
                    }
                }
            }
            $occupation = !empty($occupations) ? implode(', ', $occupations) : ($ind['occupation'] ?? null);

            return [
                'id' => $ind['id'],
                'name' => $ind['name'] ?? 'Unknown',
                'given_name' => $ind['given_name'] ?? '',
                'surname' => $ind['surname'] ?? '',
                'all_names' => $ind['all_names'] ?? [],
                'sex' => $ind['sex'] ?? 'U',
                'birth_date' => $ind['birth_date'] ?? null,
                'birth_place' => GedcomParserService::cleanPlace($ind['birth_place'] ?? null),
                'birth_year' => $ind['birth_year'] ?? null,
                'death_date' => $ind['death_date'] ?? null,
                'death_place' => GedcomParserService::cleanPlace($ind['death_place'] ?? null),
                'death_year' => $ind['death_year'] ?? null,
                'death_note' => null,
                'burial_date' => $ind['burial_date'] ?? null,
                'burial_place' => GedcomParserService::cleanPlace($ind['burial_place'] ?? null),
                'occupation' => $occupation,
                'marriage_date' => $mInfo['date'],
                'marriage_year' => $mInfo['year'],
                'marriage_place' => GedcomParserService::cleanPlace($mInfo['place']),
                'marriage_spouse_name' => $mInfo['spouse_name'],
                'marriage_type' => $mInfo['type'] ?? 'Marriage',
                'relationship_type' => $mInfo['type'] ?? null,
                'primary_media' => $ind['primary_media'] ?? null,
                'portrait_url' => $ind['portrait_url'] ?? $ind['primary_media']['portrait_url'] ?? null,
            ];
        };

        $buildAncestorTree = function (string $personId, int $depth = 0) use (&$buildAncestorTree, &$formatMiniSpouses, $data, $ancestorMaxDepth, $allowedMap, $formatPersonData) {
            if ($depth >= $ancestorMaxDepth || !isset($data['individuals'][$personId]) || ($allowedMap !== null && !isset($allowedMap[$personId]))) {
                return null;
            }

            $ind = $data['individuals'][$personId];
            $node = $formatPersonData($ind);
            $node['spouses'] = $formatMiniSpouses($ind['spouses'] ?? [], false);
            $node['parents'] = [];

            foreach ($ind['parents'] as $pId) {
                $pNode = $buildAncestorTree($pId, $depth + 1);
                if ($pNode) {
                    $node['parents'][] = $pNode;
                }
            }

            return $node;
        };

        $formatMiniSpouses = function (array $spouseIds, bool $includeDetails = false) use ($data, $allowedMap, $formatPersonData, $buildAncestorTree, &$getSiblingsForPerson) {
            $result = [];
            foreach ($spouseIds as $sId) {
                if ($allowedMap !== null && !isset($allowedMap[$sId])) continue;
                if (!isset($data['individuals'][$sId])) continue;
                $s = $data['individuals'][$sId];
                $spouseData = $formatPersonData($s);

                if ($includeDetails) {
                    $spouseData['ancestors'] = $buildAncestorTree($sId);
                    $spouseData['siblings'] = $getSiblingsForPerson($sId);
                }

                $result[] = $spouseData;
            }
            return $result;
        };

        $buildDescendantTree = function (string $personId, int $depth = 0) use (&$buildDescendantTree, $data, $descendantMaxDepth, $allowedMap, $formatMiniSpouses, $formatPersonData) {
            if ($depth >= $descendantMaxDepth || !isset($data['individuals'][$personId]) || ($allowedMap !== null && !isset($allowedMap[$personId]))) {
                return null;
            }

            $ind = $data['individuals'][$personId];
            $node = $formatPersonData($ind);
            $node['spouses'] = $formatMiniSpouses($ind['spouses'] ?? []);
            $node['children'] = [];

            foreach ($ind['children'] as $cId) {
                $cNode = $buildDescendantTree($cId, $depth + 1);
                if ($cNode) {
                    $node['children'][] = $cNode;
                }
            }

            return $node;
        };

        $getSiblingsForPerson = function (string $personId) use ($data, $allowedMap, $formatPersonData, &$formatMiniSpouses, &$buildDescendantTree, $descendantLevels) {
            $siblingsList = [];
            $ind = $data['individuals'][$personId] ?? null;
            if (! $ind) return [];

            $parentIds = $ind['parents'] ?? [];
            $siblingIdsSet = [];

            foreach ($parentIds as $pId) {
                if (isset($data['individuals'][$pId])) {
                    $parentInd = $data['individuals'][$pId];
                    foreach ($parentInd['children'] ?? [] as $childId) {
                        if ($childId !== $personId) {
                            $siblingIdsSet[$childId] = true;
                        }
                    }
                }
            }

            foreach (array_keys($siblingIdsSet) as $sibId) {
                if ($allowedMap !== null && ! isset($allowedMap[$sibId])) {
                    continue;
                }
                if (isset($data['individuals'][$sibId])) {
                    $sib = $data['individuals'][$sibId];
                    $sibData = $formatPersonData($sib);
                    $sibData['spouses'] = $formatMiniSpouses($sib['spouses'] ?? []);

                    if ($descendantLevels > 0) {
                        $sibTree = $buildDescendantTree($sibId, 0);
                        $sibData['children'] = $sibTree['children'] ?? [];
                    } else {
                        $sibData['children'] = [];
                    }

                    $siblingsList[] = $sibData;
                }
            }

            usort($siblingsList, function ($a, $b) {
                $yA = $a['birth_year'] ?? 9999;
                $yB = $b['birth_year'] ?? 9999;
                if ($yA !== $yB) {
                    return $yA <=> $yB;
                }
                return strcmp($a['name'] ?? '', $b['name'] ?? '');
            });

            return $siblingsList;
        };

        $focusInd = $data['individuals'][$id] ?? null;
        $primaryData = null;
        if ($focusInd) {
            $primaryData = $formatPersonData($focusInd);
            $primaryData['spouses'] = $formatMiniSpouses($focusInd['spouses'] ?? [], includeDetails: true);
        }
        $descendantsTree = $descendantLevels > 0 ? $buildDescendantTree($id) : null;
        if ($primaryData && $descendantsTree) {
            $primaryData['children'] = $descendantsTree['children'] ?? [];
        }
        $siblingsList = $focusInd ? $getSiblingsForPerson($id) : [];

        return response()->json([
            'primary' => $primaryData,
            'siblings' => $siblingsList,
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
            foreach ($ind['media_items'] ?? [] as $mItem) {
                $mId = $mItem['id'];
                $objectPeopleMap[$mId][] = [
                    'id' => $ind['id'],
                    'name' => $ind['name'],
                    'portrait_url' => $ind['portrait_url'] ?? null,
                    'crop' => $mItem['crop'] ?? null,
                    'css' => $mItem['css'] ?? null,
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

    public function servePortrait(Request $request, string $id, GedcomParserService $parser)
    {
        $data = $parser->getOrParseData();
        $targetId = trim($id, '@');

        if (!isset($data['individuals'][$targetId])) {
            abort(404, 'Person not found');
        }

        $person = $data['individuals'][$targetId];
        $primary = $person['primary_media'] ?? null;

        if (!$primary || empty($primary['file'])) {
            abort(404, 'No portrait image found');
        }

        $filename = $primary['file'];
        $crop = $primary['crop'] ?? null;

        $sourcePath = storage_path('app/public/gedcom/media/' . basename($filename));
        if (!File::exists($sourcePath)) {
            $sourcePath = storage_path('app/private/' . basename($filename));
        }

        if (!File::exists($sourcePath)) {
            abort(404, 'Source image file not found');
        }

        // If no crop is specified, serve the original media
        if (!$crop) {
            return $this->serveMedia($filename);
        }

        $cropsDir = storage_path('app/public/gedcom/crops');
        File::ensureDirectoryExists($cropsDir);
        $cachedCropPath = $cropsDir . '/' . $targetId . '_' . pathinfo($filename, PATHINFO_FILENAME) . '_v2.jpg';

        if (!File::exists($cachedCropPath) || File::size($cachedCropPath) === 0) {
            $parser->cropFaceImage($sourcePath, $crop, $cachedCropPath);
        }

        if (File::exists($cachedCropPath) && File::size($cachedCropPath) > 0) {
            return response()->file($cachedCropPath, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        }

        return $this->serveMedia($filename);
    }

    public function serveCropMedia(string $filename): BinaryFileResponse
    {
        $safeFilename = basename($filename);
        $path = storage_path('app/public/gedcom/crops/' . $safeFilename);

        if (!File::exists($path)) {
            abort(404, 'Cropped media file not found');
        }

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
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

    public function lineage(Request $request, string $id, GedcomParserService $parser, LineagePermissionService $lineageService)
    {
        $data = $parser->getOrParseData();

        // 1. Strict permission enforcement
        $allowedIds = $lineageService->getAllowedPersonIds($request->user(), $data['individuals']);
        $allowedMap = $allowedIds !== null ? array_flip($allowedIds) : null;

        if (!isset($data['individuals'][$id]) || ($allowedMap !== null && !isset($allowedMap[$id]))) {
            return response()->json(['error' => 'Person not found or access denied'], 404);
        }

        $rootPerson = $data['individuals'][$id];

        // Helper for Ahnentafel relationship titles
        $getRelationshipTitle = function (int $n) {
            if ($n === 1) return 'Primary Individual';
            if ($n === 2) return 'Father';
            if ($n === 3) return 'Mother';
            if ($n === 4) return 'Paternal Grandfather';
            if ($n === 5) return 'Paternal Grandmother';
            if ($n === 6) return 'Maternal Grandfather';
            if ($n === 7) return 'Maternal Grandmother';

            $gen = (int) floor(log($n, 2));
            $isMale = ($n % 2 === 0);
            $genderTitle = $isMale ? 'Grandfather' : 'Grandmother';

            $gtPrefix = match ($gen) {
                3 => 'Great-',
                4 => '2nd Great-',
                5 => '3rd Great-',
                default => ($gen - 2) . 'th Great-',
            };

            $paternal = (($n >> ($gen - 1)) & 1) === 0;
            $branch = $paternal ? 'Paternal' : 'Maternal';

            return "{$branch} {$gtPrefix}{$genderTitle}";
        };

        $getGenTitle = function (int $gen) {
            return match ($gen) {
                0 => 'Primary Individual',
                1 => 'Parents',
                2 => 'Grandparents',
                3 => 'Great-Grandparents',
                4 => '2nd Great-Grandparents',
                5 => '3rd Great-Grandparents',
                default => ($gen - 2) . 'th Great-Grandparents',
            };
        };

        // Recursive traversal to collect ALL ancestors with Ahnentafel numbers
        $ancestorsByGen = [];
        $allAncestorsList = [];
        $maxGen = 0;

        $traverseAncestors = function (string $currId, int $ahnentafelNum, int $gen) use (&$traverseAncestors, &$ancestorsByGen, &$allAncestorsList, &$maxGen, $data, $allowedMap, $getRelationshipTitle, $getGenTitle) {
            if ($allowedMap !== null && !isset($allowedMap[$currId])) {
                return;
            }
            if (!isset($data['individuals'][$currId])) {
                return;
            }

            $ind = $data['individuals'][$currId];
            if ($gen > $maxGen) {
                $maxGen = $gen;
            }

            $record = [
                'id' => $ind['id'],
                'ahnentafel_number' => $ahnentafelNum,
                'generation' => $gen,
                'generation_title' => $getGenTitle($gen),
                'relationship_title' => $getRelationshipTitle($ahnentafelNum),
                'name' => $ind['name'],
                'sex' => $ind['sex'],
                'birth_date' => $ind['birth_date'] ?? null,
                'birth_year' => $ind['birth_year'] ?? null,
                'birth_place' => $ind['birth_place'] ?? null,
                'death_date' => $ind['death_date'] ?? null,
                'death_year' => $ind['death_year'] ?? null,
                'death_place' => $ind['death_place'] ?? null,
                'primary_media' => $ind['primary_media'] ?? null,
                'parents' => $ind['parents'] ?? [],
            ];

            $allAncestorsList[] = $record;
            if (!isset($ancestorsByGen[$gen])) {
                $ancestorsByGen[$gen] = [
                    'generation' => $gen,
                    'generation_title' => $getGenTitle($gen),
                    'ancestors' => [],
                ];
            }
            $ancestorsByGen[$gen]['ancestors'][] = $record;

            $parents = $ind['parents'] ?? [];
            if (!empty($parents)) {
                $fatherId = $parents[0] ?? null;
                $motherId = $parents[1] ?? null;

                if ($fatherId) {
                    $traverseAncestors($fatherId, $ahnentafelNum * 2, $gen + 1);
                }
                if ($motherId) {
                    $traverseAncestors($motherId, $ahnentafelNum * 2 + 1, $gen + 1);
                }
            }
        };

        $traverseAncestors($id, 1, 0);

        ksort($ancestorsByGen);

        return response()->json([
            'root_person' => [
                'id' => $rootPerson['id'],
                'name' => $rootPerson['name'],
                'birth_year' => $rootPerson['birth_year'] ?? null,
                'primary_media' => $rootPerson['primary_media'] ?? null,
            ],
            'total_ancestors_count' => count($allAncestorsList) - 1,
            'max_generations_depth' => $maxGen,
            'generations' => array_values($ancestorsByGen),
            'all_ancestors' => $allAncestorsList,
        ]);
    }
}

