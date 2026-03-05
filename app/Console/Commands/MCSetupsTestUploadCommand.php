<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\MCSetupsLicense;
use Pterodactyl\Services\Servers\MCSetups\MCSetupsS3Service;

class MCSetupsTestUploadCommand extends Command
{
    protected $signature = 'mcsetups:test-upload
                            {--product : Test product upload (default)}
                            {--addon : Test addon upload instead}';

    protected $description = 'Test MCSetups product/addon upload using the configured license and S3 credentials';

    public function handle(MCSetupsS3Service $s3Service): int
    {
        $license = MCSetupsLicense::first();

        if (!$license) {
            $this->error('No MCSetups license found. Configure one at /admin/mcsetups first.');

            return 1;
        }

        if (!$license->hasS3Config()) {
            $this->error('S3 storage is not configured. Set S3 endpoint, access key, secret key, and bucket in the license form.');

            return 1;
        }

        $this->info('License found: store_url=' . $license->store_url);
        $this->info('S3: endpoint=' . $license->s3_endpoint . ', bucket=' . $license->s3_bucket);

        $testAddon = $this->option('addon');

        if ($testAddon) {
            return $this->testAddonUpload($s3Service, $license);
        }

        return $this->testProductUpload($s3Service, $license);
    }

    private function testProductUpload(MCSetupsS3Service $s3Service, MCSetupsLicense $license): int
    {
        $this->info('Creating test product (zip + metadata)...');

        $displayName = 'Test Product ' . time();
        $zipPath = sys_get_temp_dir() . '/mcsetups-test-product-' . time() . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->error('Failed to create temp zip file.');

            return 1;
        }
        $zip->addFromString('readme.txt', 'Test product for MCSetups upload verification.');
        $zip->close();

        $ext = 'zip';
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $displayName) . '_' . time() . '.' . $ext;
        $path = 'mcsetups-addons/products/' . $safeName;
        $base = pathinfo($path, PATHINFO_FILENAME);

        $meta = [
            'display_name' => $displayName,
            'description' => 'Test product uploaded via mcsetups:test-upload',
            'game_version' => '1.20.1',
            'category' => 'Test',
            'placeholders' => [],
            'required_addon_ids' => [],
            'optional_addon_ids' => [],
        ];

        try {
            $disk = $s3Service->getDisk($license);
            $disk->put($path, file_get_contents($zipPath), 'public');
            $disk->put('mcsetups-addons/products/' . $base . '.json', json_encode($meta, JSON_PRETTY_PRINT), 'public');

            Cache::forget('mcsetups:list_products');

            @unlink($zipPath);

            $this->info('Product uploaded successfully.');
            $this->info('Path: ' . $path);
            $this->info('URL: ' . $disk->url($path));

            $products = $s3Service->listProducts($license);
            $this->info('Total products in S3: ' . count($products));

            return 0;
        } catch (\Throwable $e) {
            @unlink($zipPath);
            $this->error('Upload failed: ' . $e->getMessage());

            return 1;
        }
    }

    private function testAddonUpload(MCSetupsS3Service $s3Service, MCSetupsLicense $license): int
    {
        $this->info('Creating test addon (zip)...');

        $displayName = 'Test Addon ' . time();
        $zipPath = sys_get_temp_dir() . '/mcsetups-test-addon-' . time() . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->error('Failed to create temp zip file.');

            return 1;
        }
        $zip->addFromString('manifest.json', '{"format_version":1}');
        $zip->close();

        $ext = 'zip';
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $displayName) . '_' . time() . '.' . $ext;
        $path = 'mcsetups-addons/' . $safeName;

        try {
            $disk = $s3Service->getDisk($license);
            $disk->put($path, file_get_contents($zipPath), 'public');

            Cache::forget('mcsetups:list_addons');

            @unlink($zipPath);

            $this->info('Addon uploaded successfully.');
            $this->info('Path: ' . $path);
            $this->info('URL: ' . $disk->url($path));

            $addons = $s3Service->listAddons($license);
            $this->info('Total addons in S3: ' . count($addons));

            return 0;
        } catch (\Throwable $e) {
            @unlink($zipPath);
            $this->error('Upload failed: ' . $e->getMessage());

            return 1;
        }
    }
}
