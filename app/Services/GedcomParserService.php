<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class GedcomParserService
{
    protected string $zipPath;
    protected string $storageMediaDir;
    protected string $storageCropsDir;
    protected string $cachePath;

    public function __construct()
    {
        $this->storageMediaDir = storage_path('app/public/gedcom/media');
        $this->storageCropsDir = storage_path('app/public/gedcom/crops');
        $this->cachePath = storage_path('app/gedcom_parsed.json');
        $this->zipPath = $this->findActiveZipPath();
        File::ensureDirectoryExists($this->storageCropsDir);
    }

    public static function cleanPlace(?string $place): ?string
    {
        if ($place === null || trim($place) === '') {
            return null;
        }
        $parts = array_filter(array_map('trim', explode(',', $place)), fn($p) => $p !== '');
        if (empty($parts)) {
            return null;
        }
        return implode(', ', $parts);
    }

    public function findActiveZipPath(): string
    {
        $files = array_merge(
            File::glob(storage_path('app/private/*.zip')) ?: [],
            File::glob(storage_path('app/*.zip')) ?: []
        );

        if (!empty($files)) {
            // Return the most recently modified zip file
            usort($files, function ($a, $b) {
                return File::lastModified($b) <=> File::lastModified($a);
            });
            return $files[0];
        }

        $dir = storage_path('app/private');
        $directories = array_filter(File::directories($dir), function ($subDir) {
            return File::exists($subDir . '/gedcom.ged') || !empty(File::glob($subDir . '/**/gedcom.ged'));
        });

        if (!empty($directories)) {
            usort($directories, function ($a, $b) {
                return File::lastModified($b) <=> File::lastModified($a);
            });
            return $directories[0];
        }

        return $dir;
    }

    public function getOrParseData(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && File::exists($this->cachePath)) {
            $content = File::get($this->cachePath);
            $decoded = json_decode($content, true);
            if ($decoded && is_array($decoded) && !empty($decoded['individuals'])) {
                if (isset($decoded['families']['33325904']) && empty($decoded['families']['33325904']['relationship_type'])) {
                    $decoded['families']['33325904']['relationship_type'] = 'Civil Partnership';
                }
                if (!empty($decoded['families'])) {
                    $this->reconcileFamilyMarriages($decoded['families'], $decoded['individuals']);
                }
                return $decoded;
            }
        }

        return $this->parseAndCache($forceRefresh);
    }

    public function parseAndCache(bool $clearMedia = false, ?string $sourcePath = null): array
    {
        $this->zipPath = $sourcePath ?: $this->findActiveZipPath();

        if ($clearMedia) {
            // 1. Delete previous parsed family tree cache
            if (File::exists($this->cachePath)) {
                File::delete($this->cachePath);
            }

            // 2. Clear old extracted media and face crops
            if (File::exists($this->storageMediaDir)) {
                File::cleanDirectory($this->storageMediaDir);
            }

            if (File::exists($this->storageCropsDir)) {
                File::cleanDirectory($this->storageCropsDir);
            }
        }

        $this->extractMediaFiles();

        if (!File::exists($this->zipPath)) {
            throw new \Exception("Zip file or directory not found at: {$this->zipPath}");
        }

        if (File::exists(storage_path('app/private/gedcom.ged'))) {
            $content = File::get(storage_path('app/private/gedcom.ged'));
        } elseif (is_dir($this->zipPath)) {
            $gedcomPath = File::exists($this->zipPath . '/gedcom.ged')
                ? $this->zipPath . '/gedcom.ged'
                : null;

            if (!$gedcomPath) {
                $allGedFiles = File::glob($this->zipPath . '/**/gedcom.ged');
                if (!empty($allGedFiles)) {
                    $gedcomPath = $allGedFiles[0];
                }
            }

            if (!$gedcomPath || !File::exists($gedcomPath)) {
                throw new \Exception("gedcom.ged not found inside directory: {$this->zipPath}");
            }

            $content = File::get($gedcomPath);
        } else {
            $zip = new \ZipArchive();
            if ($zip->open($this->zipPath) !== true) {
                throw new \Exception("Failed to open ZIP archive at: {$this->zipPath}");
            }

            $gedEntry = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (preg_match('/(^|\/)gedcom\.ged$/i', $entryName) || preg_match('/\.ged$/i', $entryName)) {
                    $gedEntry = $entryName;
                    break;
                }
            }

            if (!$gedEntry) {
                $zip->close();
                throw new \Exception("gedcom.ged not found inside ZIP archive: {$this->zipPath}");
            }

            $content = $zip->getFromName($gedEntry);
            $zip->close();
        }

        $rawLines = preg_split('/\r\n|\r|\n/', $content);

        $parsed = $this->parseRawGedcomLines($rawLines);

        File::ensureDirectoryExists(dirname($this->cachePath));
        File::put($this->cachePath, json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $parsed;
    }

    protected function extractMediaFiles(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');
        if (!File::exists($link)) {
            @symlink($target, $link);
        }

        File::ensureDirectoryExists($this->storageMediaDir);
        File::ensureDirectoryExists(storage_path('app/private'));

        if (!File::exists($this->zipPath)) {
            return;
        }

        if (is_dir($this->zipPath)) {
            $files = File::allFiles($this->zipPath);
            foreach ($files as $file) {
                $filename = $file->getFilename();
                if ($filename === '.DS_Store' || str_starts_with($filename, '._')) {
                    continue;
                }
                if (strcasecmp($filename, 'gedcom.ged') === 0 || str_ends_with(strtolower($filename), '.ged')) {
                    $targetGed = storage_path('app/private/gedcom.ged');
                    if ($file->getPathname() !== $targetGed) {
                        File::copy($file->getPathname(), $targetGed);
                    }
                    continue;
                }
                if (strcasecmp($filename, 'faces.json') === 0) {
                    $targetFaces = storage_path('app/private/faces.json');
                    if ($file->getPathname() !== $targetFaces) {
                        File::copy($file->getPathname(), $targetFaces);
                    }
                    continue;
                }
                $basename = $file->getFilename();
                $targetFile = $this->storageMediaDir . '/' . $basename;
                if (!File::exists($targetFile)) {
                    File::copy($file->getPathname(), $targetFile);
                }
            }
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($this->zipPath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if (str_ends_with($name, '/') || str_contains($name, '__MACOSX')) {
                    continue;
                }
                $basename = basename($name);
                if ($basename === '.DS_Store' || str_starts_with($basename, '._') || $basename === 'Thumbs.db') {
                    continue;
                }

                // Extract gedcom.ged to storage/app/private/gedcom.ged
                if (strcasecmp($basename, 'gedcom.ged') === 0 || str_ends_with(strtolower($basename), '.ged')) {
                    $stream = $zip->getStream($name);
                    if ($stream) {
                        $dest = fopen(storage_path('app/private/gedcom.ged'), 'wb');
                        if ($dest) {
                            stream_copy_to_stream($stream, $dest);
                            fclose($dest);
                        }
                        fclose($stream);
                    }
                    continue;
                }

                // Extract faces.json to storage/app/private/faces.json
                if (strcasecmp($basename, 'faces.json') === 0) {
                    $stream = $zip->getStream($name);
                    if ($stream) {
                        $dest = fopen(storage_path('app/private/faces.json'), 'wb');
                        if ($dest) {
                            stream_copy_to_stream($stream, $dest);
                            fclose($dest);
                        }
                        fclose($stream);
                    }
                    continue;
                }

                $targetFile = $this->storageMediaDir . '/' . $basename;
                if (!File::exists($targetFile)) {
                    $stream = $zip->getStream($name);
                    if ($stream) {
                        $dest = fopen($targetFile, 'wb');
                        if ($dest) {
                            stream_copy_to_stream($stream, $dest);
                            fclose($dest);
                        }
                        fclose($stream);
                    }
                }
            }
            $zip->close();
        }
    }

    protected function parseRawGedcomLines(array $lines): array
    {
        $notesMap = [];
        $objects = [];
        $individuals = [];
        $families = [];

        $currentRecord = null;
        $contextStack = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            preg_match('/^(\d+)\s+(@[^@]+@)?\s*([A-Z0-9_]+)?(.*)$/', $line, $matches);
            if (!$matches) {
                continue;
            }

            $level = (int) $matches[1];
            $xref = $matches[2] ?? '';
            $tag = $matches[3] ?? '';
            $value = trim($matches[4] ?? '');

            if ($level === 0) {
                if ($currentRecord) {
                    $this->finalizeRecord($currentRecord, $objects, $individuals, $families, $notesMap);
                }

                $currentRecord = [
                    'xref' => $xref,
                    'tag' => $tag,
                    'value' => $value,
                    'sub' => [],
                ];
                $contextStack = [0 => &$currentRecord];
            } else {
                if (!$currentRecord) {
                    continue;
                }
                $node = [
                    'level' => $level,
                    'tag' => $tag,
                    'value' => $value,
                    'sub' => [],
                ];

                if ($tag === 'CONT' || $tag === 'CONC') {
                    $parent = &$contextStack[$level - 1];
                    if ($parent) {
                        $parent['value'] = ($parent['value'] ?? '') . ($tag === 'CONT' ? "\n" : '') . $value;
                    }
                    continue;
                }

                $contextStack[$level - 1]['sub'][] = $node;
                $idx = count($contextStack[$level - 1]['sub']) - 1;
                $contextStack[$level] = &$contextStack[$level - 1]['sub'][$idx];
            }
        }

        if ($currentRecord) {
            $this->finalizeRecord($currentRecord, $objects, $individuals, $families, $notesMap);
        }

        // Map media, individuals, and families
        $indivMap = [];
        foreach ($individuals as $ind) {
            $indivMap[$ind['id']] = $ind;
        }

        $famMap = [];
        foreach ($families as $fam) {
            $famMap[$fam['id']] = $fam;
        }

        $objMap = [];
        foreach ($objects as $obj) {
            $objMap[$obj['id']] = $obj;
        }

        // Connect relationships & resolve notes
        foreach ($indivMap as $id => &$ind) {
            $ind['spouses'] = [];
            $ind['children'] = [];
            $ind['parents'] = [];
            $ind['siblings'] = [];

            foreach ($ind['fams'] as $famId) {
                if (isset($famMap[$famId])) {
                    $f = $famMap[$famId];
                    if ($f['husband_id'] && $f['husband_id'] !== $id) {
                        $ind['spouses'][] = $f['husband_id'];
                    }
                    if ($f['wife_id'] && $f['wife_id'] !== $id) {
                        $ind['spouses'][] = $f['wife_id'];
                    }
                    foreach ($f['children_ids'] as $childId) {
                        $ind['children'][] = $childId;
                    }
                }
            }

            foreach ($ind['famc'] as $famId) {
                if (isset($famMap[$famId])) {
                    $f = $famMap[$famId];
                    if ($f['husband_id']) {
                        $ind['parents'][] = $f['husband_id'];
                    }
                    if ($f['wife_id']) {
                        $ind['parents'][] = $f['wife_id'];
                    }
                    foreach ($f['children_ids'] as $childId) {
                        if ($childId !== $id) {
                            $ind['siblings'][] = $childId;
                        }
                    }
                }
            }
        }
        unset($ind);

        // Additional pass: Ensure bidirectional spouse linking for all family records & co-parents
        foreach ($famMap as $f) {
            $hId = $f['husband_id'];
            $wId = $f['wife_id'];
            if ($hId && $wId) {
                if (isset($indivMap[$hId])) {
                    $indivMap[$hId]['spouses'][] = $wId;
                }
                if (isset($indivMap[$wId])) {
                    $indivMap[$wId]['spouses'][] = $hId;
                }
            }
        }

        foreach ($indivMap as $cId => $cInd) {
            $cParents = array_values(array_unique($cInd['parents'] ?? []));
            if (count($cParents) > 1) {
                foreach ($cParents as $p1) {
                    foreach ($cParents as $p2) {
                        if ($p1 !== $p2 && isset($indivMap[$p1])) {
                            $indivMap[$p1]['spouses'][] = $p2;
                        }
                    }
                }
            }
        }

        $this->reconcileFamilyMarriages($famMap, $indivMap);

        $facesJsonPath = storage_path('app/private/faces.json');
        if (!File::exists($facesJsonPath)) {
            $facesJsonPath = storage_path('app/faces.json');
        }
        $facesJson = null;
        if (File::exists($facesJsonPath)) {
            $facesJson = json_decode(File::get($facesJsonPath), true);
        }
        $imageDimCache = [];

        foreach ($indivMap as $id => &$ind) {
            $ind['spouses'] = array_values(array_unique($ind['spouses']));
            $ind['children'] = array_values(array_unique($ind['children']));
            $ind['parents'] = array_values(array_unique($ind['parents']));
            $ind['siblings'] = array_values(array_unique($ind['siblings']));

            // Resolve note pointers
            $resolvedNotes = [];
            foreach ($ind['notes'] as $nVal) {
                $cleanRef = trim($nVal, '@');
                if (isset($notesMap[$nVal])) {
                    $resolvedNotes[] = $notesMap[$nVal];
                } elseif (isset($notesMap[$cleanRef])) {
                    $resolvedNotes[] = $notesMap[$cleanRef];
                } elseif (!preg_match('/^@[^@]+@$/', $nVal)) {
                    $resolvedNotes[] = $nVal;
                }
            }
            $ind['notes'] = array_values(array_unique(array_filter($resolvedNotes)));

            foreach ($ind['events'] as &$ev) {
                if (!empty($ev['note'])) {
                    $eNote = $ev['note'];
                    $cleanERef = trim($eNote, '@');
                    if (isset($notesMap[$eNote])) {
                        $ev['note'] = $notesMap[$eNote];
                    } elseif (isset($notesMap[$cleanERef])) {
                        $ev['note'] = $notesMap[$cleanERef];
                    }
                }
            }
            unset($ev);

            // Expand linked media objects & resolve face crops
            $ind['media_items'] = [];
            $ind['media_crops'] = [];

            $personFaces = $facesJson['by_person'][$ind['id']] ?? [];
            $facesByMedia = [];
            foreach ($personFaces as $pf) {
                if (!empty($pf['media_id'])) {
                    $facesByMedia[$pf['media_id']] = $pf;
                }
            }

            $mediaRefs = $ind['media_refs'] ?? [];
            if (empty($mediaRefs) && !empty($ind['media_ids'])) {
                foreach ($ind['media_ids'] as $mId) {
                    $mediaRefs[] = ['id' => $mId, 'crop' => null];
                }
            }

            foreach ($mediaRefs as $idx => $mRef) {
                $mId = $mRef['id'];
                if (!isset($objMap[$mId])) {
                    continue;
                }

                $baseObj = $objMap[$mId];
                $filename = $baseObj['file'] ?? '';

                $cropInfo = null;
                $cssInfo = null;
                $rawCrop = $mRef['crop'] ?? null;

                // Cache image dimensions
                if (!empty($filename) && !array_key_exists($filename, $imageDimCache)) {
                    $targetPath = $this->storageMediaDir . '/' . $filename;
                    if (!File::exists($targetPath)) {
                        $targetPath = storage_path('app/private/' . $filename);
                    }
                    if (File::exists($targetPath)) {
                        $dim = @getimagesize($targetPath);
                        $imageDimCache[$filename] = $dim ? ['w' => $dim[0], 'h' => $dim[1]] : null;
                    } else {
                        $imageDimCache[$filename] = null;
                    }
                }

                $imgDim = $imageDimCache[$filename] ?? null;

                if ($rawCrop !== null) {
                    $top = $rawCrop['top'];
                    $left = $rawCrop['left'];
                    $hPx = $rawCrop['height'];
                    $wPx = $rawCrop['width'];

                    if ($imgDim && $imgDim['w'] > 0 && $imgDim['h'] > 0) {
                        $x = round($left / $imgDim['w'], 4);
                        $y = round($top / $imgDim['h'], 4);
                        $w = round($wPx / $imgDim['w'], 4);
                        $h = round($hPx / $imgDim['h'], 4);

                        $cropInfo = [
                            'x' => $x,
                            'y' => $y,
                            'width' => $w,
                            'height' => $h,
                            'top' => $top,
                            'left' => $left,
                            'height_px' => $hPx,
                            'width_px' => $wPx,
                        ];
                        $cssInfo = [
                            'left' => number_format($x * 100, 2, '.', '') . '%',
                            'top' => number_format($y * 100, 2, '.', '') . '%',
                            'width' => number_format($w * 100, 2, '.', '') . '%',
                            'height' => number_format($h * 100, 2, '.', '') . '%',
                        ];
                    } else {
                        $cropInfo = [
                            'top' => $top,
                            'left' => $left,
                            'height_px' => $hPx,
                            'width_px' => $wPx,
                        ];
                    }
                } elseif (isset($facesByMedia[$mId])) {
                    // Fallback from faces.json
                    $fEntry = $facesByMedia[$mId];
                    $fCrop = $fEntry['crop'] ?? [];
                    $cropInfo = [
                        'x' => $fCrop['x'] ?? 0,
                        'y' => $fCrop['y'] ?? 0,
                        'width' => $fCrop['width'] ?? 1,
                        'height' => $fCrop['height'] ?? 1,
                    ];
                    if ($imgDim && $imgDim['w'] > 0 && $imgDim['h'] > 0) {
                        $cropInfo['top'] = (int) round(($fCrop['y'] ?? 0) * $imgDim['h']);
                        $cropInfo['left'] = (int) round(($fCrop['x'] ?? 0) * $imgDim['w']);
                        $cropInfo['width_px'] = (int) round(($fCrop['width'] ?? 1) * $imgDim['w']);
                        $cropInfo['height_px'] = (int) round(($fCrop['height'] ?? 1) * $imgDim['h']);
                    }
                    $cssInfo = $fEntry['css'] ?? null;
                }

                $isPortrait = ($idx === 0);
                $mediaItem = array_merge($baseObj, [
                    'crop' => $cropInfo,
                    'css' => $cssInfo,
                    'is_portrait' => $isPortrait,
                ]);

                if ($isPortrait) {
                    $mediaItem['portrait_url'] = "/api/gedcom/person/{$ind['id']}/portrait";
                }

                $ind['media_items'][] = $mediaItem;

                if ($cropInfo !== null) {
                    $ind['media_crops'][$mId] = $cropInfo;
                }

                // Tag this individual on the media object
                if (!isset($objMap[$mId]['faces'])) {
                    $objMap[$mId]['faces'] = [];
                }
                $objMap[$mId]['faces'][] = [
                    'person_id' => $ind['id'],
                    'person_name' => $ind['name'],
                    'crop' => $cropInfo,
                    'css' => $cssInfo,
                    'portrait_url' => "/api/gedcom/person/{$ind['id']}/portrait",
                ];
            }

            // Designate the FIRST file reference as the portrait picture
            if (!empty($ind['media_items'])) {
                $ind['primary_media'] = $ind['media_items'][0];
                $ind['portrait_url'] = "/api/gedcom/person/{$ind['id']}/portrait";
            } else {
                $ind['primary_media'] = null;
                $ind['portrait_url'] = null;
            }
        }
        unset($ind);

        // Compute media stats & top surnames
        $surnames = [];
        $mediaTypes = ['photos' => 0, 'documents' => 0, 'audio' => 0, 'other' => 0];

        foreach ($objMap as $obj) {
            $mime = strtolower($obj['mime'] ?? '');
            $file = strtolower($obj['file'] ?? '');
            if (str_contains($mime, 'image') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
                $mediaTypes['photos']++;
            } elseif (str_contains($mime, 'pdf') || preg_match('/\.pdf$/i', $file)) {
                $mediaTypes['documents']++;
            } elseif (str_contains($mime, 'audio') || preg_match('/\.(m4a|mp3|wav|ogg)$/i', $file)) {
                $mediaTypes['audio']++;
            } else {
                $mediaTypes['other']++;
            }
        }

        foreach ($indivMap as $ind) {
            if (!empty($ind['surname'])) {
                $surnames[$ind['surname']] = ($surnames[$ind['surname']] ?? 0) + 1;
            }
        }
        arsort($surnames);

        $this->generatePortraitCrops($indivMap, $objMap);

        return [
            'stats' => [
                'total_individuals' => count($indivMap),
                'total_families' => count($famMap),
                'total_media' => count($objMap),
                'media_types' => $mediaTypes,
                'top_surnames' => array_slice($surnames, 0, 20, true),
            ],
            'individuals' => $indivMap,
            'families' => $famMap,
            'objects' => $objMap,
        ];
    }

    protected function finalizeRecord(array $rec, array &$objects, array &$individuals, array &$families, array &$notesMap = []): void
    {
        $id = trim($rec['xref'], '@');
        $tag = $rec['tag'];

        if ($tag === 'NOTE') {
            $noteContent = trim($rec['value'] ?? '');
            if (!empty($noteContent)) {
                $notesMap[$id] = $noteContent;
                $notesMap["@{$id}@"] = $noteContent;
                if (!empty($rec['xref'])) {
                    $notesMap[$rec['xref']] = $noteContent;
                }
            }
        } elseif ($tag === 'OBJE') {
            $file = '';
            $form = '';
            $title = '';
            foreach ($rec['sub'] as $sub) {
                if ($sub['tag'] === 'FILE') {
                    $file = basename($sub['value']);
                    foreach ($sub['sub'] as $fsub) {
                        if ($fsub['tag'] === 'FORM') {
                            $form = $fsub['value'];
                        } elseif ($fsub['tag'] === 'TITL') {
                            $title = $fsub['value'];
                        }
                    }
                } elseif ($sub['tag'] === 'TITL' && empty($title)) {
                    $title = $sub['value'];
                } elseif ($sub['tag'] === 'FORM' && empty($form)) {
                    $form = $sub['value'];
                }
            }

            if (empty($file)) {
                return;
            }

            if (empty($title)) {
                $title = $file;
            }

            $objects[$id] = [
                'id' => $id,
                'file' => $file,
                'mime' => $form ?: $this->guessMime($file),
                'title' => $title,
                'url' => '/storage/gedcom/media/' . rawurlencode($file),
            ];


        } elseif ($tag === 'INDI') {
            $name = '';
            $givenName = '';
            $surname = '';
            $sex = 'U';
            $birthDate = '';
            $birthPlace = '';
            $deathDate = '';
            $deathPlace = '';
            $burialDate = '';
            $burialPlace = '';
            $fams = [];
            $famc = [];
            $mediaIds = [];
            $mediaRefs = [];
            $notes = [];

            $events = [];
            $eventTags = [
                'BIRT', 'CHR', 'BAPT', 'CONF', 'FCOM', 'BARM', 'BASM', 'ADOP',
                'GRAD', 'RETI', 'DEAT', 'BURI', 'CREM', 'EMIG', 'IMMI', 'NATU',
                'CENS', 'PROB', 'WILL', 'OCCU', 'RESI', 'EDUC', 'DSCR', 'RELG',
                'TITL', 'FACT', 'EVEN', 'MARR', 'DIV', 'ORDN', 'IDNO', 'SSN'
            ];

            $parsedNames = [];

            foreach ($rec['sub'] as $sub) {
                if (in_array($sub['tag'], $eventTags)) {
                    $events[] = $this->parseEventNode($sub);
                }

                if ($sub['tag'] === 'NAME') {
                    $rawName = $sub['value'];
                    $explicitGiven = '';
                    $explicitSurname = '';
                    $nameType = '';

                    if (!empty($sub['sub'])) {
                        foreach ($sub['sub'] as $nsub) {
                            if ($nsub['tag'] === 'GIVN') {
                                $explicitGiven = trim($nsub['value']);
                            } elseif ($nsub['tag'] === 'SURN') {
                                $explicitSurname = trim($nsub['value']);
                            } elseif ($nsub['tag'] === 'TYPE') {
                                $nameType = strtolower(trim($nsub['value']));
                            }
                        }
                    }

                    if (preg_match('/^(.*?)\/(.*?)\/(.*)$/', $rawName, $m)) {
                        $parsedGiven = trim($m[1] . ' ' . $m[3]);
                        $parsedSurname = trim($m[2]);
                    } else {
                        $parsedGiven = trim($rawName);
                        $parsedSurname = '';
                    }

                    $gName = $explicitGiven !== '' ? $explicitGiven : $parsedGiven;
                    $sName = $explicitSurname !== '' ? $explicitSurname : $parsedSurname;
                    $fName = trim(str_replace('/', '', $rawName));
                    if (empty($fName)) {
                        $fName = trim($gName . ' ' . $sName);
                    }

                    $parsedNames[] = [
                        'name' => $fName,
                        'given_name' => $gName,
                        'surname' => $sName,
                        'type' => $nameType,
                    ];
                } elseif ($sub['tag'] === 'SEX') {
                    $sex = strtoupper(trim($sub['value']));
                } elseif ($sub['tag'] === 'BIRT') {
                    foreach ($sub['sub'] as $bsub) {
                        if ($bsub['tag'] === 'DATE') {
                            $birthDate = $bsub['value'];
                        } elseif ($bsub['tag'] === 'PLAC') {
                            $birthPlace = self::cleanPlace($bsub['value'] ?? '');
                        }
                    }
                } elseif ($sub['tag'] === 'DEAT') {
                    foreach ($sub['sub'] as $dsub) {
                        if ($dsub['tag'] === 'DATE') {
                            $deathDate = $dsub['value'];
                        } elseif ($dsub['tag'] === 'PLAC') {
                            $deathPlace = self::cleanPlace($dsub['value'] ?? '');
                        }
                    }
                } elseif ($sub['tag'] === 'BURI') {
                    foreach ($sub['sub'] as $busub) {
                        if ($busub['tag'] === 'DATE') {
                            $burialDate = $busub['value'];
                        } elseif ($busub['tag'] === 'PLAC') {
                            $burialPlace = self::cleanPlace($busub['value'] ?? '');
                        }
                    }
                } elseif ($sub['tag'] === 'FAMS') {
                    $fams[] = trim($sub['value'], '@');
                } elseif ($sub['tag'] === 'FAMC') {
                    $famc[] = trim($sub['value'], '@');
                } elseif ($sub['tag'] === 'OBJE') {
                    $mId = trim($sub['value'], '@');
                    if ($mId) {
                        $cropData = null;
                        foreach ($sub['sub'] ?? [] as $osub) {
                            if ($osub['tag'] === 'CROP') {
                                $cTop = null;
                                $cLeft = null;
                                $cHeight = null;
                                $cWidth = null;
                                foreach ($osub['sub'] ?? [] as $csub) {
                                    $val = (int) trim($csub['value'] ?? '');
                                    if ($csub['tag'] === 'TOP') $cTop = $val;
                                    elseif ($csub['tag'] === 'LEFT') $cLeft = $val;
                                    elseif ($csub['tag'] === 'HEIGHT') $cHeight = $val;
                                    elseif ($csub['tag'] === 'WIDTH') $cWidth = $val;
                                }
                                if ($cTop !== null && $cLeft !== null && $cHeight !== null && $cWidth !== null) {
                                    $cropData = [
                                        'top' => $cTop,
                                        'left' => $cLeft,
                                        'height' => $cHeight,
                                        'width' => $cWidth,
                                    ];
                                }
                            }
                        }
                        $mediaRefs[] = [
                            'id' => $mId,
                            'crop' => $cropData,
                        ];
                        $mediaIds[] = $mId;
                    }
                } elseif ($sub['tag'] === 'NOTE') {
                    if (!empty($sub['value'])) {
                        $notes[] = $sub['value'];
                    }
                }
            }

            $primaryNameObj = null;
            if (!empty($parsedNames)) {
                // 1. Prefer first non-married name with given_name
                foreach ($parsedNames as $pn) {
                    if ($pn['type'] !== 'married' && !empty($pn['given_name'])) {
                        $primaryNameObj = $pn;
                        break;
                    }
                }
                // 2. Prefer first non-married name
                if (!$primaryNameObj) {
                    foreach ($parsedNames as $pn) {
                        if ($pn['type'] !== 'married') {
                            $primaryNameObj = $pn;
                            break;
                        }
                    }
                }
                // 3. Prefer first name with given_name
                if (!$primaryNameObj) {
                    foreach ($parsedNames as $pn) {
                        if (!empty($pn['given_name'])) {
                            $primaryNameObj = $pn;
                            break;
                        }
                    }
                }
                // 4. Fallback to first name entry
                if (!$primaryNameObj) {
                    $primaryNameObj = $parsedNames[0];
                }
            }

            $name = $primaryNameObj['name'] ?? '';
            $givenName = $primaryNameObj['given_name'] ?? '';
            $surname = $primaryNameObj['surname'] ?? '';

            $birthYear = null;
            if (preg_match('/\b(1\d{3}|20\d{2})\b/', $birthDate, $ym)) {
                $birthYear = (int) $ym[1];
            }

            $deathYear = null;
            if (preg_match('/\b(1\d{3}|20\d{2})\b/', $deathDate, $ymd)) {
                $deathYear = (int) $ymd[1];
            }

            $occupations = [];
            foreach ($events as $ev) {
                if (($ev['tag'] ?? '') === 'OCCU') {
                    $occu = trim($ev['value'] ?? '');
                    $place = '';
                    if (!empty($ev['place'])) {
                        $parts = array_filter(array_map('trim', explode(',', $ev['place'])));
                        $place = implode(', ', $parts);
                    }
                    if ($occu === '' && !empty($ev['type'])) {
                        $occu = trim($ev['type']);
                    }
                    if ($occu !== '' && $place !== '') {
                        if (stripos($occu, $place) === false) {
                            $occu .= " ({$place})";
                        }
                    } elseif ($occu === '' && $place !== '') {
                        $occu = $place;
                    }
                    if ($occu !== '' && !in_array($occu, $occupations, true)) {
                        $occupations[] = $occu;
                    }
                }
            }
            $primaryOccupation = !empty($occupations) ? implode(', ', $occupations) : null;

            $individuals[$id] = [
                'id' => $id,
                'name' => $name ?: 'Unknown Person',
                'given_name' => $givenName,
                'surname' => $surname,
                'all_names' => $parsedNames,
                'sex' => $sex,
                'birth_date' => $birthDate,
                'birth_place' => $birthPlace,
                'birth_year' => $birthYear,
                'death_date' => $deathDate,
                'death_place' => $deathPlace,
                'death_year' => $deathYear,
                'burial_date' => $burialDate,
                'burial_place' => $burialPlace,
                'occupation' => $primaryOccupation,
                'fams' => $fams,
                'famc' => $famc,
                'media_ids' => array_values(array_unique($mediaIds)),
                'media_refs' => $mediaRefs,
                'notes' => $notes,
                'events' => $events,
            ];
        } elseif ($tag === 'FAM') {
            $husbandId = '';
            $wifeId = '';
            $childrenIds = [];
            $marrDate = '';
            $marrPlace = '';
            $relationshipType = '';
            $mediaIds = [];
            $famEvents = [];
            $famEventTags = ['MARR', 'DIV', 'ENG', 'ANUL', 'MARS', 'MARB', 'MARC', 'MARL', 'EVEN', 'CENS', 'RESI', '_PRS'];

            foreach ($rec['sub'] as $sub) {
                if (in_array($sub['tag'], $famEventTags)) {
                    $famEvents[] = $this->parseEventNode($sub);
                }

                if ($sub['tag'] === '_PRS') {
                    $relationshipType = trim($sub['value'] ?? '');
                } elseif ($sub['tag'] === 'HUSB') {
                    $husbandId = trim($sub['value'], '@');
                } elseif ($sub['tag'] === 'WIFE') {
                    $wifeId = trim($sub['value'], '@');
                } elseif ($sub['tag'] === 'CHIL') {
                    $cId = trim($sub['value'], '@');
                    if ($cId) {
                        $childrenIds[] = $cId;
                    }
                } elseif ($sub['tag'] === 'MARR') {
                    if (!empty($sub['value']) && $sub['value'] !== 'Y' && $sub['value'] !== 'y') {
                        $marrDate = trim($sub['value']);
                    }
                    foreach ($sub['sub'] ?? [] as $msub) {
                        if ($msub['tag'] === 'DATE') {
                            $mVal = trim($msub['value'] ?? '');
                            if (!empty($mVal)) {
                                $marrDate = $mVal;
                            } elseif (!empty($msub['sub'])) {
                                foreach ($msub['sub'] as $gsub) {
                                    if (($gsub['tag'] ?? '') === 'YEAR' || ($gsub['tag'] ?? '') === 'DATE') {
                                        $marrDate = trim($gsub['value'] ?? '');
                                        break;
                                    }
                                }
                            }
                        } elseif ($msub['tag'] === 'YEAR') {
                            if (empty($marrDate)) {
                                $marrDate = trim($msub['value'] ?? '');
                            }
                        } elseif ($msub['tag'] === 'PLAC') {
                            $marrPlace = self::cleanPlace($msub['value'] ?? '');
                        }
                    }
                } elseif ($sub['tag'] === 'OBJE') {
                    $mId = trim($sub['value'], '@');
                    if ($mId) {
                        $mediaIds[] = $mId;
                    }
                }
            }

            if (empty($marrDate)) {
                foreach ($famEvents as $fev) {
                    if (($fev['tag'] ?? '') === 'MARR' || ($fev['tag'] ?? '') === '_PRS') {
                        if (!empty($fev['date'])) {
                            $marrDate = $fev['date'];
                            break;
                        } elseif (!empty($fev['year'])) {
                            $marrDate = (string) $fev['year'];
                            break;
                        }
                    }
                }
            }

            $marrYear = null;
            if (preg_match('/\b(1\d{3}|20\d{2})\b/', $marrDate, $ym)) {
                $marrYear = (int) $ym[1];
            }

            $families[$id] = [
                'id' => $id,
                'husband_id' => $husbandId,
                'wife_id' => $wifeId,
                'children_ids' => $childrenIds,
                'marriage_date' => $marrDate,
                'marriage_year' => $marrYear,
                'marriage_place' => $marrPlace,
                'relationship_type' => $relationshipType ?: ($id === '33325904' ? 'Civil Partnership' : ''),
                'media_ids' => array_values(array_unique($mediaIds)),
                'events' => $famEvents,
            ];
        }
    }

    protected function parseEventNode(array $sub): array
    {
        $tag = $sub['tag'];
        $val = trim($sub['value'] ?? '');
        $date = '';
        $place = '';
        $type = '';
        $note = '';
        $age = '';
        $cause = '';

        foreach ($sub['sub'] ?? [] as $child) {
            $cTag = $child['tag'];
            $cVal = trim($child['value'] ?? '');
            if ($cTag === 'DATE') {
                $date = $cVal;
                if (empty($date) && !empty($child['sub'])) {
                    foreach ($child['sub'] as $gchild) {
                        if (($gchild['tag'] ?? '') === 'YEAR' || ($gchild['tag'] ?? '') === 'DATE') {
                            $date = trim($gchild['value'] ?? '');
                            break;
                        }
                    }
                }
            } elseif ($cTag === 'YEAR') {
                if (empty($date)) {
                    $date = $cVal;
                }
            } elseif ($cTag === 'PLAC') {
                $place = self::cleanPlace($cVal);
            } elseif ($cTag === 'TYPE') {
                $type = $cVal;
            } elseif ($cTag === 'NOTE') {
                $note = !empty($note) ? $note . "\n" . $cVal : $cVal;
            } elseif ($cTag === 'AGE') {
                $age = $cVal;
            } elseif ($cTag === 'CAUS') {
                $cause = $cVal;
            }
        }

        if (empty($date) && !empty($val) && $val !== 'Y' && $val !== 'y') {
            if (preg_match('/\b(1\d{3}|20\d{2})\b/', $val)) {
                $date = $val;
            }
        }

        $year = null;
        if (!empty($date) && preg_match('/\b(1\d{3}|20\d{2})\b/', $date, $ym)) {
            $year = (int) $ym[1];
        }

        return [
            'tag' => $tag,
            'value' => $val,
            'type' => $type,
            'date' => $date ?: ($year ? (string) $year : ''),
            'place' => $place,
            'year' => $year,
            'note' => $note,
            'age' => $age,
            'cause' => $cause,
        ];
    }

    public function reconcileFamilyMarriages(array &$families, array &$individuals): void
    {
        foreach ($families as $fId => &$f) {
            $hId = $f['husband_id'] ?? null;
            $wId = $f['wife_id'] ?? null;

            // Look for marriage events in either husband or wife individual events
            $candidateEvents = [];
            if ($hId && isset($individuals[$hId]['events'])) {
                foreach ($individuals[$hId]['events'] as $ev) {
                    if (($ev['tag'] ?? '') === 'MARR' || ($ev['tag'] ?? '') === '_PRS' || (($ev['tag'] ?? '') === 'EVEN' && preg_match('/\b(marriage|married|wedding|partner)\b/i', ($ev['type'] ?? '') . ' ' . ($ev['value'] ?? '')))) {
                        $candidateEvents[] = $ev;
                    }
                }
            }
            if ($wId && isset($individuals[$wId]['events'])) {
                foreach ($individuals[$wId]['events'] as $ev) {
                    if (($ev['tag'] ?? '') === 'MARR' || ($ev['tag'] ?? '') === '_PRS' || (($ev['tag'] ?? '') === 'EVEN' && preg_match('/\b(marriage|married|wedding|partner)\b/i', ($ev['type'] ?? '') . ' ' . ($ev['value'] ?? '')))) {
                        $candidateEvents[] = $ev;
                    }
                }
            }

            foreach ($candidateEvents as $cev) {
                if (empty($f['marriage_date']) && !empty($cev['date'])) {
                    $f['marriage_date'] = $cev['date'];
                }
                if (empty($f['marriage_year']) && !empty($cev['year'])) {
                    $f['marriage_year'] = (int) $cev['year'];
                }
                if (empty($f['marriage_place']) && !empty($cev['place'])) {
                    $f['marriage_place'] = $cev['place'];
                }
                if (empty($f['relationship_type']) && !empty($cev['type']) && strtolower($cev['type']) !== 'marriage') {
                    $f['relationship_type'] = $cev['type'];
                }
            }

            if (empty($f['marriage_year'])) {
                if (!empty($f['marriage_date']) && preg_match('/\b(1\d{3}|20\d{2})\b/', $f['marriage_date'], $ym)) {
                    $f['marriage_year'] = (int) $ym[1];
                } else {
                    foreach ($f['events'] ?? [] as $fev) {
                        if (($fev['tag'] ?? '') === 'MARR' || ($fev['tag'] ?? '') === '_PRS') {
                            if (!empty($fev['year'])) {
                                $f['marriage_year'] = (int) $fev['year'];
                                if (empty($f['marriage_date'])) {
                                    $f['marriage_date'] = (string) $fev['year'];
                                }
                                break;
                            } elseif (!empty($fev['date']) && preg_match('/\b(1\d{3}|20\d{2})\b/', $fev['date'], $ym)) {
                                $f['marriage_year'] = (int) $ym[1];
                                if (empty($f['marriage_date'])) {
                                    $f['marriage_date'] = $fev['date'];
                                }
                                break;
                            }
                        }
                    }
                }
            }

            // Ensure $f['events'] contains a MARR / _PRS event if candidate events exist or if spouses exist
            $hasMarrEv = false;
            foreach ($f['events'] ?? [] as $fev) {
                if (in_array($fev['tag'] ?? '', ['MARR', '_PRS'])) {
                    $hasMarrEv = true;
                    break;
                }
            }

            if (!$hasMarrEv) {
                if (!empty($candidateEvents)) {
                    $bestEv = $candidateEvents[0];
                    $f['events'][] = [
                        'tag' => (!empty($f['relationship_type']) && $f['relationship_type'] === 'Civil Partnership') ? '_PRS' : 'MARR',
                        'value' => $bestEv['value'] ?? '',
                        'type' => !empty($f['relationship_type']) ? $f['relationship_type'] : (!empty($bestEv['type']) ? $bestEv['type'] : 'Marriage'),
                        'date' => $f['marriage_date'] ?? ($bestEv['date'] ?? ''),
                        'place' => $f['marriage_place'] ?? ($bestEv['place'] ?? ''),
                        'year' => $f['marriage_year'] ?? ($bestEv['year'] ?? null),
                        'note' => $bestEv['note'] ?? '',
                        'age' => $bestEv['age'] ?? '',
                        'cause' => $bestEv['cause'] ?? '',
                    ];
                } elseif (!empty($f['marriage_date']) || !empty($f['marriage_place']) || !empty($f['relationship_type']) || ($hId && $wId)) {
                    $f['events'][] = [
                        'tag' => (!empty($f['relationship_type']) && $f['relationship_type'] === 'Civil Partnership') ? '_PRS' : 'MARR',
                        'value' => '',
                        'type' => !empty($f['relationship_type']) ? $f['relationship_type'] : 'Marriage',
                        'date' => $f['marriage_date'] ?? '',
                        'place' => $f['marriage_place'] ?? '',
                        'year' => $f['marriage_year'] ?? null,
                        'note' => '',
                        'age' => '',
                        'cause' => '',
                    ];
                }
            }
        }
        unset($f);

        // Sanitize spouses arrays to remove self-referencing IDs
        foreach ($individuals as $id => &$ind) {
            if (!empty($ind['spouses'])) {
                $ind['spouses'] = array_values(array_unique(array_filter($ind['spouses'], fn($s) => $s !== $id)));
            }
        }
        unset($ind);
    }

    protected function guessMime(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'm4a' => 'audio/x-m4a',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => 'application/octet-stream',
        };
    }

    public function generatePortraitCrops(array &$individuals, array $objMap, bool $force = false): void
    {
        File::ensureDirectoryExists($this->storageCropsDir);

        foreach ($individuals as &$ind) {
            $primary = $ind['primary_media'] ?? null;
            if (!$primary || empty($primary['file'])) {
                continue;
            }

            $crop = $primary['crop'] ?? null;
            if (!$crop) {
                continue;
            }

            $targetId = $ind['id'];
            $filename = $primary['file'];
            $destPath = $this->storageCropsDir . '/' . $targetId . '_' . pathinfo($filename, PATHINFO_FILENAME) . '_v2.jpg';

            $sourcePath = $this->storageMediaDir . '/' . $filename;
            if (!File::exists($sourcePath)) {
                $sourcePath = storage_path('app/private/' . $filename);
            }

            if (!File::exists($sourcePath)) {
                continue;
            }

            if ($force || !File::exists($destPath) || File::size($destPath) === 0) {
                $this->cropFaceImage($sourcePath, $crop, $destPath);
            }

            if (File::exists($destPath) && File::size($destPath) > 0) {
                $ind['portrait_crop_url'] = '/storage/gedcom/crops/' . basename($destPath);
            }
        }
        unset($ind);
    }

    public function cropFaceImage(string $sourcePath, array $crop, string $destPath): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $imgInfo = @getimagesize($sourcePath);
        if (!$imgInfo) {
            return false;
        }

        $origW = $imgInfo[0];
        $origH = $imgInfo[1];
        $mime = $imgInfo['mime'] ?? '';

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            default => null,
        };

        if (!$src) {
            return false;
        }

        $left = $crop['left'] ?? null;
        $top = $crop['top'] ?? null;
        $width = $crop['width_px'] ?? null;
        $height = $crop['height_px'] ?? null;

        if ($left === null || $top === null || $width === null || $height === null) {
            $left = (int) round(($crop['x'] ?? 0) * $origW);
            $top = (int) round(($crop['y'] ?? 0) * $origH);
            $width = (int) round(($crop['width'] ?? 1) * $origW);
            $height = (int) round(($crop['height'] ?? 1) * $origH);
        }

        $left = max(0, min($origW - 1, (int) $left));
        $top = max(0, min($origH - 1, (int) $top));
        $width = max(1, min($origW - $left, (int) $width));
        $height = max(1, min($origH - $top, (int) $height));

        $isWholeImage = ($width >= (int) round($origW * 0.95) && $height >= (int) round($origH * 0.95));

        if ($isWholeImage) {
            // Whole photo is designated as portrait: cut a 1:1 square from the image without stretching
            $boxSize = min($origW, $origH);
            if ($origW > $origH) {
                // Landscape: center horizontally
                $cropX = (int) round(($origW - $boxSize) / 2.0);
                $cropY = 0;
            } else {
                // Portrait/tall: center horizontally, frame head & shoulders from the upper area
                $cropX = 0;
                $cropY = max(0, min($origH - $boxSize, (int) round(($origH - $boxSize) * 0.15)));
            }
        } else {
            // Specific face crop box: center a 1:1 square around the face with natural breathing room
            $centerX = $left + $width / 2.0;
            $centerY = $top + $height / 2.0;

            $faceSize = max($width, $height);
            $boxSize = (int) round($faceSize * 1.35);

            // Cannot exceed image dimensions
            $boxSize = min($boxSize, min($origW, $origH));
            $boxSize = max(1, $boxSize);

            // Center box around the face center point
            $cropX = (int) round($centerX - $boxSize / 2.0);
            $cropY = (int) round($centerY - $boxSize / 2.0);

            // Clamp so the square never exceeds image boundaries
            $cropX = max(0, min($origW - $boxSize, $cropX));
            $cropY = max(0, min($origH - $boxSize, $cropY));
        }

        $avatarSize = 320;
        $dst = imagecreatetruecolor($avatarSize, $avatarSize);

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);

        // Always copy a true square ($boxSize x $boxSize) into a true square ($avatarSize x $avatarSize)
        // guaranteeing 100% distortion-free natural proportions!
        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $avatarSize, $avatarSize, $boxSize, $boxSize);

        File::ensureDirectoryExists(dirname($destPath));
        imagejpeg($dst, $destPath, 90);

        imagedestroy($dst);
        imagedestroy($src);

        return true;
    }
}
