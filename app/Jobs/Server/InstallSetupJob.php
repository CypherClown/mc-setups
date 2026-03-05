<?php

namespace Pterodactyl\Jobs\Server;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Servers\StartupModificationService;

class InstallSetupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; 
    public $tries = 72; 

    protected Server $server;
    protected ?Egg $originalEgg;

    public function __construct(Server $server, ?Egg $originalEgg = null)
    {
        $this->server = $server;
        $this->originalEgg = $originalEgg;
    }

    public function handle(
        StartupModificationService $startupModificationService,
        DaemonFileRepository $fileRepository
    ): void {
        $this->server->refresh();
        
        $shouldContinue = $this->waitForInstallationCompletion();
        
        if (!$shouldContinue) {
            return;
        }
        
        $this->server->refresh();
        $finalStatus = $this->server->status;
        
        if ($finalStatus !== Server::STATUS_INSTALL_FAILED && $finalStatus !== Server::STATUS_REINSTALL_FAILED && $finalStatus !== Server::STATUS_INSTALLING) {
            $this->restoreToPaperEgg($startupModificationService);
        }
    }

    protected function waitForInstallationCompletion(): bool
    {
        $this->server->refresh();
        
        $currentStatus = $this->server->status;
        
        if ($currentStatus === Server::STATUS_INSTALL_FAILED || $currentStatus === Server::STATUS_REINSTALL_FAILED) {
            return true;
        }
        
        if ($currentStatus !== Server::STATUS_INSTALLING) {
            $hasFiles = $this->verifyFilesInstalled();
            
            if (!$hasFiles && $currentStatus === null) {
                Log::warning('MCSetups: Wings reported installation complete but no files found', [
                    'server_id' => $this->server->id,
                ]);
                $this->server->update([
                    'status' => Server::STATUS_REINSTALL_FAILED,
                ]);
                return true;
            }
            
            return true;
        }

        $this->checkInstallationStatus();
        
        $this->server->refresh();
        if ($this->server->status === Server::STATUS_INSTALLING) {
            $elapsedTime = $this->getElapsedInstallationTime();
            
            if ($elapsedTime > 3600) {
                Log::error('MCSetups: Installation timeout - marking as failed', [
                    'server_id' => $this->server->id,
                    'elapsed_seconds' => $elapsedTime,
                ]);
                
                $this->server->update([
                    'status' => Server::STATUS_INSTALL_FAILED,
                ]);
                return true;
            } else {
                $this->release(30);
                return false;
            }
        }
        
        return true;
    }

    protected function getElapsedInstallationTime(): int
    {
        $installedAt = $this->server->installed_at;
        if ($installedAt) {
            return now()->diffInSeconds($installedAt);
        }
        
        return 0;
    }

    protected function checkInstallationStatus(): void
    {
        try {
            $this->server->refresh();
            
            try {
                $daemonRepo = app(\Pterodactyl\Repositories\Wings\DaemonServerRepository::class);
                $daemonRepo->setServer($this->server);
                $details = $daemonRepo->getDetails();
                
                if (isset($details['state']) && $details['state'] !== 'installing') {
                    $this->checkIfInstallationCompleted();
                }
            } catch (\Exception $e) {
            }
            
            $this->checkIfInstallationCompleted();
            
        } catch (\Exception $e) {
            Log::error('MCSetups: Error checking installation status', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalize Wings API response: can be { data: [...] } or root array.
     */
    protected function getFileListFromResponse(array $files): array
    {
        if (isset($files['data']) && is_array($files['data'])) {
            return $files['data'];
        }
        if (isset($files[0]) && is_array($files[0])) {
            return $files;
        }
        return [];
    }

    protected function checkIfInstallationCompleted(): void
    {
        try {
            $fileRepository = app(DaemonFileRepository::class);
            $fileRepository->setServer($this->server);
            
            $files = $fileRepository->getDirectory('/');
            $fileList = $this->getFileListFromResponse(is_array($files) ? $files : []);
            $fileCount = count($fileList);
            
            $hasServerFiles = false;
            $hasSetupZip = false;
            
            $serverFileIndicators = [
                'server.properties', 'eula.txt', 'server.jar', 'spigot.yml', 'bukkit.yml', 'paper.yml',
                'pom.xml', 'build.gradle', 'fabric-server-launch.jar', 'run.sh', 'run.bat', 'start.sh',
            ];
            
            foreach ($fileList as $file) {
                $fileName = $file['name'] ?? '';
                
                if (in_array($fileName, $serverFileIndicators)) {
                    $hasServerFiles = true;
                }
                
                if ($fileName === 'setup.zip') {
                    $hasSetupZip = true;
                }
            }
            
            if ($hasServerFiles && !$hasSetupZip && $fileCount >= 2) {
                if ($this->server->status === Server::STATUS_INSTALLING) {
                    $this->server->update([
                        'status' => null,
                        'installed_at' => now(),
                    ]);
                    $this->server->refresh();
                    try {
                        app(\Pterodactyl\Repositories\Wings\DaemonServerRepository::class)
                            ->setServer($this->server)
                            ->sync();
                    } catch (\Exception $e) {
                    }
                }
            } elseif ($fileCount === 0 && $this->server->status === null) {
                Log::error('MCSetups:Job [3/5] Installation reported complete but no files found - marking as failed', [
                    'server_id' => $this->server->id,
                ]);
                $this->server->update([
                    'status' => Server::STATUS_REINSTALL_FAILED,
                ]);
            }
            
        } catch (\Exception $e) {
        }
    }

    protected function verifyFilesInstalled(): bool
    {
        try {
            $fileRepository = app(DaemonFileRepository::class);
            $fileRepository->setServer($this->server);
            
            $files = $fileRepository->getDirectory('/');
            $fileList = $this->getFileListFromResponse(is_array($files) ? $files : []);
            $fileCount = count($fileList);
            
            if ($fileCount === 0) {
                return false;
            }
            
            $serverFileIndicators = [
                'server.properties', 'eula.txt', 'server.jar', 'spigot.yml', 'bukkit.yml', 'paper.yml',
                'pom.xml', 'build.gradle', 'config', 'plugins', 'mods', 'fabric-server-launch.jar', 'run.sh', 'run.bat', 'start.sh',
            ];
            
            $hasServerFiles = false;
            foreach ($fileList as $file) {
                $fileName = $file['name'] ?? '';
                if (in_array($fileName, $serverFileIndicators)) {
                    $hasServerFiles = true;
                    break;
                }
            }
            
            return $hasServerFiles && $fileCount >= 2;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Find the Paper egg by name and author (official Pterodactyl egg).
     */
    protected function findPaperEgg(): ?Egg
    {
        return Egg::where('name', 'Paper')
            ->where('author', 'parker@pterodactyl.io')
            ->first();
    }

    protected function restoreToPaperEgg(StartupModificationService $startupModificationService): void
    {
        $egg = $this->findPaperEgg();
        if (!$egg) {
            return;
        }

        try {
            $defaultImage = null;
            try {
                $raw = $egg->getRawOriginal('docker_images');
                $dockerImages = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
                if (is_array($dockerImages) && !empty($dockerImages)) {
                    $defaultImage = array_values($dockerImages)[0];
                }
            } catch (\Throwable $e) {
            }

            DB::table('servers')
                ->where('id', $this->server->id)
                ->update([
                    'egg_id' => $egg->id,
                    'nest_id' => $egg->nest_id,
                    'status' => null,
                    'installed_at' => now(),
                ]);

            $this->server->refresh();

            try {
                app(\Pterodactyl\Repositories\Wings\DaemonServerRepository::class)
                    ->setServer($this->server)
                    ->sync();
            } catch (\Exception $e) {
            }

            $startupData = [
                'nest_id' => $egg->nest_id,
                'egg_id' => $egg->id,
                'startup' => $egg->startup ?? $this->server->startup,
            ];
            if ($defaultImage) {
                $startupData['docker_image'] = $defaultImage;
            }

            $startupModificationService->setUserLevel(User::USER_LEVEL_ADMIN);
            $startupModificationService->handle($this->server, $startupData);

            $this->server->refresh();
        } catch (\Exception $e) {
            Log::error('MCSetups:Job [4/5] Failed to restore to Paper egg', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

