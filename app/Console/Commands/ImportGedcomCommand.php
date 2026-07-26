<?php

namespace App\Console\Commands;

use App\Services\GedcomParserService;
use Illuminate\Console\Command;

class ImportGedcomCommand extends Command
{
    protected $signature = 'gedcom:import {--force : Force re-parse and refresh cache} {--clean : Wipe old extracted media directory before re-parsing}';

    protected $description = 'Extract media and parse the active GEDCOM zip file into cached JSON format';

    public function handle(GedcomParserService $parser): int
    {
        $this->info('Starting GEDCOM import and media extraction...');
        $force = (bool) $this->option('force') || (bool) $this->option('clean');

        try {
            $data = $parser->parseAndCache($force);
            $this->info('GEDCOM import completed successfully!');

            $this->table(['Metric', 'Count'], [
                ['Total Individuals', $data['stats']['total_individuals']],
                ['Total Families', $data['stats']['total_families']],
                ['Total Media Objects', $data['stats']['total_media']],
                ['Photos', $data['stats']['media_types']['photos']],
                ['Documents', $data['stats']['media_types']['documents']],
                ['Audio Clips', $data['stats']['media_types']['audio']],
            ]);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to import GEDCOM: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
