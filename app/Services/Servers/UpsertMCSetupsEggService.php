<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Http\UploadedFile;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Services\Eggs\Sharing\EggImporterService;
use Pterodactyl\Services\Eggs\Sharing\EggUpdateImporterService;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class UpsertMCSetupsEggService
{
    protected EggImporterService $eggImporter;
    protected EggUpdateImporterService $eggUpdater;

    public function __construct(
        EggImporterService $eggImporter,
        EggUpdateImporterService $eggUpdater
    ) {
        $this->eggImporter = $eggImporter;
        $this->eggUpdater = $eggUpdater;
    }

    public function handle(): Egg
    {
        $eggFilePath = base_path('database/Seeders/eggs/egg-mcsetups-installer.json');
        
        $file = UploadedFile::createFromBase(
            new SymfonyUploadedFile($eggFilePath, 'database/Seeders/eggs/egg-mcsetups-installer.json')
        );

        $decoded = json_decode(file_get_contents($eggFilePath), true);
        $eggName = $decoded['name'] ?? 'MCSetups Installer';
        
        $existingEgg = Egg::where('author', 'support@hxdev.org')
            ->where('name', $eggName)
            ->first();
        
        if ($existingEgg) {
            $this->eggUpdater->handle($existingEgg, $file);
            return $existingEgg->refresh();
        } else {
            $firstNestId = Nest::first()->id;
            return $this->eggImporter->handle($file, $firstNestId);
        }
    }
}