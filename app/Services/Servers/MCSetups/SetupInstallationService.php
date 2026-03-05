<?php

namespace Pterodactyl\Services\Servers\MCSetups;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Egg;
use Pterodactyl\Jobs\Server\InstallSetupJob;
use Pterodactyl\Services\Servers\UpsertMCSetupsEggService;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\StartupModificationService;
use Illuminate\Support\Facades\Log;

class SetupInstallationService
{
    protected UpsertMCSetupsEggService $eggService;
    protected DaemonPowerRepository $powerRepository;
    protected DaemonServerRepository $serverRepository;
    protected StartupModificationService $startupService;

    public function __construct(
        UpsertMCSetupsEggService $eggService,
        DaemonPowerRepository $powerRepository,
        DaemonServerRepository $serverRepository,
        StartupModificationService $startupService
    ) {
        $this->eggService = $eggService;
        $this->powerRepository = $powerRepository;
        $this->serverRepository = $serverRepository;
        $this->startupService = $startupService;
    }

    public function initiateInstallation(Server $server, array $data): void
    {
        Log::info('Initiating MCSetups installation', [
            'server_id' => $server->id,
            'download_url' => $data['download_url'] ?? 'N/A',
        ]);

        $setupEgg = $this->eggService->handle();

        if ($server->egg_id === $setupEgg->id) {
            throw new \Exception('A setup installation is currently running for this server.');
        }

        $this->powerRepository->setServer($server)->send('kill');

        $originalEgg = $server->egg;

        $server->forceFill([
            'nest_id' => $setupEgg->nest_id,
            'egg_id' => $setupEgg->id,
            'status' => Server::STATUS_INSTALLING,
        ])->save();

        $server->refresh();

        $this->startupService->setUserLevel(User::USER_LEVEL_ADMIN)->handle($server, [
            'egg_id' => $setupEgg->id,
            'environment' => [
                'SETUP_DOWNLOAD_URL' => $data['download_url'],
                'SETUP_WIPE_DATA' => ($data['wipe_data'] ?? false) ? 'true' : 'false',
                'SETUP_ZIP_AND_WIPE' => ($data['zip_and_wipe'] ?? false) ? 'true' : 'false',
            ],
        ]);

        $this->serverRepository->setServer($server)->reinstall();

        InstallSetupJob::dispatch($server, $originalEgg);

        Log::info('MCSetups installation initiated', [
            'server_id' => $server->id,
        ]);
    }
}


