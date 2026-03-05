<?php

namespace Pterodactyl\Console\Commands;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Eggs\Sharing\EggImporterService;
use Pterodactyl\Services\Eggs\Sharing\EggUpdateImporterService;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class SeedMCSetupsEggCommand extends Command
{
    protected $signature = 'seed:mcsetups-egg';
    protected $description = 'Seed the MCSetups installer egg';

    public function __construct(
        protected EggImporterService $importer,
        protected EggUpdateImporterService $updater
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $eggPath = base_path('database/Seeders/eggs/egg-mcsetups-installer.json');
        
        if (!file_exists($eggPath)) {
            $this->error('MCSetups egg file not found: ' . $eggPath);
            return 1;
        }

        $file = UploadedFile::createFromBase(
            new SymfonyUploadedFile($eggPath, 'egg-mcsetups-installer.json')
        );

        $egg = Egg::where('name', 'MCSetups Installer')
            ->where('author', 'support@hxdev.org')
            ->first();

        $nestId = Nest::first()->id;

        if ($egg) {
            $this->updater->handle($egg, $file);
            $this->info('MCSetups egg updated (ID: ' . $egg->id . ')');
        } else {
            $newEgg = $this->importer->handle($file, $nestId);
            $this->info('MCSetups egg created (ID: ' . $newEgg->id . ')');
        }

        return 0;
    }
}

