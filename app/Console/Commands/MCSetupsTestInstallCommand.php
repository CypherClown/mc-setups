<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\MCSetupsLicense;
use Pterodactyl\Http\Controllers\Api\Client\Servers\MCSetupsController;

class MCSetupsTestInstallCommand extends Command
{
    protected $signature = 'mcsetups:test-install
                            {server_uuid : Server UUID (e.g. b8096202-0d96-4fef-a7e8-fa8c8656c4ac)}
                            {--name= : Product/setup display name to install (e.g. "Mortis Skyblock Server Setup v1.0")}
                            {--file-id= : Use this file id instead of looking up by name (e.g. client-1)}
                            {--wipe : Wipe existing data before install}
                            {--zip-wipe : Zip existing data then wipe}';

    protected $description = 'Test MCSetups install: find a setup by name (or use file-id), fill default placeholders, and run install';

    public function handle(): int
    {
        $serverUuid = $this->argument('server_uuid');
        $name = $this->option('name');
        $fileId = $this->option('file-id');

        $this->line('');
        $this->info('=== MCSetups test install ===');
        $this->line('');

        $this->info('Step 1/6: Resolve server by UUID');
        $server = Server::where('uuid', $serverUuid)->first();
        if (!$server) {
            $this->error("Server not found: {$serverUuid}");
            return 1;
        }
        $this->info("  Server: {$server->name} (id: {$server->id}, uuid: {$server->uuid})");

        $this->info('Step 2/6: Load MCSetups license');
        $license = MCSetupsLicense::where('is_active', true)->first() ?? MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            $this->error('No MCSetups license with store_url and license_key configured. Set at /admin/mcsetups.');
            return 1;
        }
        $baseUrl = rtrim($license->store_url, '/');
        $apiUrl = $baseUrl . '/store/files';
        $this->info("  Store: {$baseUrl}");

        $this->info('Step 3/6: Resolve file id (by name or --file-id)');
        if (!$fileId) {
            if (!$name) {
                $this->error('Provide either --name="Mortis Skyblock Server Setup v1.0" or --file-id=client-1');
                return 1;
            }

            $this->line("  Fetching store files to find: {$name}");
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key])
                ->get($apiUrl);

            if (!$response->successful()) {
                $this->error('Store API failed: ' . $response->status() . ' ' . $response->body());
                return 1;
            }

            $data = $response->json();
            $files = $data['data']['files'] ?? $data['files'] ?? $data['data'] ?? [];
            if (!is_array($files)) {
                $files = [];
            }

            $match = null;
            $nameLower = strtolower($name);
            foreach ($files as $f) {
                $dn = isset($f['display_name']) ? strtolower((string) $f['display_name']) : '';
                if ($dn === $nameLower || str_contains($dn, $nameLower) || str_contains($nameLower, $dn)) {
                    $match = $f;
                    break;
                }
            }
            if (!$match) {
                foreach ($files as $f) {
                    $dn = isset($f['display_name']) ? (string) $f['display_name'] : '';
                    if (stripos($dn, $name) !== false) {
                        $match = $f;
                        break;
                    }
                }
            }

            if (!$match) {
                $this->error("No store file found matching: {$name}");
                $this->line('Available files: ' . implode(', ', array_map(fn ($f) => $f['display_name'] ?? $f['id'] ?? '?', array_slice($files, 0, 10))));
                return 1;
            }

            $fileId = $match['id'] ?? $match['file_id'] ?? null;
            if ($fileId === null) {
                $this->error('Store file has no id.');
                return 1;
            }
            $this->info("  Found: " . ($match['display_name'] ?? $fileId) . " (id: {$fileId})");
        }

        $fileId = is_numeric($fileId) ? (int) $fileId : (string) $fileId;

        $this->info('Step 4/6: Fetch file details and build default placeholders');
        $fileUrl = $baseUrl . '/store/files/' . $fileId;
        $fileResponse = Http::timeout(15)
            ->withHeaders(['X-License-Key' => $license->license_key])
            ->get($fileUrl);

        $placeholderValues = [];
        if ($fileResponse->successful()) {
            $fileData = $fileResponse->json();
            $file = $fileData['data']['file'] ?? $fileData['file'] ?? $fileData['data'] ?? [];
            $placeholders = $file['placeholders'] ?? [];
            foreach ($placeholders as $ph) {
                $token = $ph['token'] ?? $ph['key'] ?? '';
                if ($token === '') {
                    continue;
                }
                $example = $ph['example'] ?? $ph['default'] ?? 'My Server';
                $placeholderValues[$token] = $example;
            }
            if (!empty($placeholderValues)) {
                $this->line('  Default placeholders: ' . json_encode($placeholderValues, JSON_UNESCAPED_SLASHES));
            } else {
                $this->line('  No placeholders');
            }
        }

        $wipeData = (bool) $this->option('wipe');
        $zipAndWipe = (bool) $this->option('zip-wipe');

        $this->info('Step 5/6: Build install request (file_id, placeholders, addon_ids=[], wipe/zip-wipe)');
        $this->line("  file_id={$fileId}, wipe_data=" . ($wipeData ? 'true' : 'false') . ', zip_and_wipe=' . ($zipAndWipe ? 'true' : 'false'));

        $this->info('Step 6/6: Call MCSetupsController::install()');

        $request = Request::create('/api/client/servers/' . $server->uuid . '/mcsetups/install', 'POST', [
            'file_id' => $fileId,
            'placeholder_values' => $placeholderValues,
            'addon_ids' => [],
            'wipe_data' => $wipeData,
            'zip_and_wipe' => $zipAndWipe,
        ]);
        $request->setUserResolver(fn () => $server->user);
        $request->headers->set('Accept', 'application/json');

        try {
            $controller = app(MCSetupsController::class);
            $response = $controller->install($request, $server);
            $content = $response->getData(true);
        } catch (\Throwable $e) {
            $this->error('Install failed: ' . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }

        if ($response->getStatusCode() >= 400) {
            $this->error('Install failed: ' . ($content['error'] ?? $content['message'] ?? json_encode($content)));
            return 1;
        }

        $this->info('Install started successfully. ' . ($content['message'] ?? 'Server will reinstall with the selected setup.'));
        $this->line('');
        return 0;
    }
}
