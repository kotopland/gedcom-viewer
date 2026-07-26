<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class GedcomParserService
{
    protected string $zipPath;
    protected string $storageMediaDir;
    protected string $cachePath;

    public function __construct()
    {
        $this->storageMediaDir = storage_path('app/public/gedcom/media');
        $this->cachePath = storage_path('app/gedcom_parsed.json');
        $this->zipPath = $this->findActiveZipPath();
    }

    public function findActiveZipPath(): string
    {
        $dir = storage_path('app/private');
        $files = File::glob($dir . '/*.zip');

        if (empty($files)) {
            $directories = array_filter(File::directories($dir), function ($subDir) {
                return File::exists($subDir . '/gedcom.ged') || !empty(File::glob($subDir . '/**/gedcom.ged'));
            });

            if (!empty($directories)) {
                usort($directories, function ($a, $b) {
                    return File::lastModified($b) <=> File::lastModified($a);
                });
                return $directories[0];
            }

            if (File::exists($dir . '/gedcom.ged')) {
                return $dir;
            }

            return storage_path('app/private/Family tree of Family & Smith.zip');
        }

        // Return the most recently modified zip file
        usort($files, function ($a, $b) {
            return File::lastModified($b) <=> File::lastModified($a);
        });

        return $files[0];
    }

    public function getOrParseData(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && File::exists($this->cachePath)) {
            $content = File::get($this->cachePath);
            $decoded = json_decode($content, true);
            if ($decoded && is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->parseAndCache($forceRefresh);
    }

    public function parseAndCache(bool $clearMedia = false): array
    {
        $this->zipPath = $this->findActiveZipPath();

        if ($clearMedia && File::exists($this->storageMediaDir)) {
            File::cleanDirectory($this->storageMediaDir);
        }

        $this->extractMediaFiles();

        if (!File::exists($this->zipPath)) {
            throw new \Exception("Zip file or directory not found at: {$this->zipPath}");
        }

        if (is_dir($this->zipPath)) {
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

            $stream = $zip->getStream('gedcom.ged');
            if (!$stream) {
                $zip->close();
                throw new \Exception("gedcom.ged not found inside ZIP archive.");
            }

            $content = stream_get_contents($stream);
            fclose($stream);
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

        if (!File::exists($this->zipPath)) {
            return;
        }

        if (is_dir($this->zipPath)) {
            $files = File::allFiles($this->zipPath);
            foreach ($files as $file) {
                if ($file->getFilename() === 'gedcom.ged' || $file->getFilename() === '.DS_Store') {
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
                if ($name === 'gedcom.ged' || str_ends_with($name, '/')) {
                    continue;
                }
                $basename = basename($name);
                $targetFile = $this->storageMediaDir . '/' . $basename;
                if (!File::exists($targetFile)) {
                    $stream = $zip->getStream($name);
                    if ($stream) {
                        file_put_contents($targetFile, stream_get_contents($stream));
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

            // Expand linked media objects
            $ind['media_items'] = [];
            foreach ($ind['media_ids'] as $mId) {
                if (isset($objMap[$mId])) {
                    $ind['media_items'][] = $objMap[$mId];
                }
            }

            if (!empty($ind['media_items'])) {
                $ind['primary_media'] = $ind['media_items'][0];
            } else {
                $ind['primary_media'] = null;
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
                            $birthPlace = $bsub['value'];
                        }
                    }
                } elseif ($sub['tag'] === 'DEAT') {
                    foreach ($sub['sub'] as $dsub) {
                        if ($dsub['tag'] === 'DATE') {
                            $deathDate = $dsub['value'];
                        } elseif ($dsub['tag'] === 'PLAC') {
                            $deathPlace = $dsub['value'];
                        }
                    }
                } elseif ($sub['tag'] === 'BURI') {
                    foreach ($sub['sub'] as $busub) {
                        if ($busub['tag'] === 'DATE') {
                            $burialDate = $busub['value'];
                        } elseif ($busub['tag'] === 'PLAC') {
                            $burialPlace = $busub['value'];
                        }
                    }
                } elseif ($sub['tag'] === 'FAMS') {
                    $fams[] = trim($sub['value'], '@');
                } elseif ($sub['tag'] === 'FAMC') {
                    $famc[] = trim($sub['value'], '@');
                } elseif ($sub['tag'] === 'OBJE') {
                    $mId = trim($sub['value'], '@');
                    if ($mId) {
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
                'fams' => $fams,
                'famc' => $famc,
                'media_ids' => array_values(array_unique($mediaIds)),
                'notes' => $notes,
                'events' => $events,
            ];
        } elseif ($tag === 'FAM') {
            $husbandId = '';
            $wifeId = '';
            $childrenIds = [];
            $marrDate = '';
            $marrPlace = '';
            $mediaIds = [];
            $famEvents = [];
            $famEventTags = ['MARR', 'DIV', 'ENG', 'ANUL', 'MARS', 'MARB', 'MARC', 'MARL', 'EVEN', 'CENS', 'RESI'];

            foreach ($rec['sub'] as $sub) {
                if (in_array($sub['tag'], $famEventTags)) {
                    $famEvents[] = $this->parseEventNode($sub);
                }

                if ($sub['tag'] === 'HUSB') {
                    $husbandId = trim($sub['value'], '@');
                } elseif ($sub['tag'] === 'WIFE') {
                    $wifeId = trim($sub['value'], '@');
                } elseif ($sub['tag'] === 'CHIL') {
                    $cId = trim($sub['value'], '@');
                    if ($cId) {
                        $childrenIds[] = $cId;
                    }
                } elseif ($sub['tag'] === 'MARR') {
                    foreach ($sub['sub'] as $msub) {
                        if ($msub['tag'] === 'DATE') {
                            $marrDate = $msub['value'];
                        } elseif ($msub['tag'] === 'PLAC') {
                            $marrPlace = $msub['value'];
                        }
                    }
                } elseif ($sub['tag'] === 'OBJE') {
                    $mId = trim($sub['value'], '@');
                    if ($mId) {
                        $mediaIds[] = $mId;
                    }
                }
            }

            $families[$id] = [
                'id' => $id,
                'husband_id' => $husbandId,
                'wife_id' => $wifeId,
                'children_ids' => $childrenIds,
                'marriage_date' => $marrDate,
                'marriage_place' => $marrPlace,
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
            } elseif ($cTag === 'PLAC') {
                $place = $cVal;
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

        $year = null;
        if (preg_match('/\b(1\d{3}|20\d{2})\b/', $date, $ym)) {
            $year = (int) $ym[1];
        }

        return [
            'tag' => $tag,
            'value' => $val,
            'type' => $type,
            'date' => $date,
            'place' => $place,
            'year' => $year,
            'note' => $note,
            'age' => $age,
            'cause' => $cause,
        ];
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
}
