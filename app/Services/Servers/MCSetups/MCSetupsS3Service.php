<?php

namespace Pterodactyl\Services\Servers\MCSetups;

use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\MCSetupsLicense;

class MCSetupsS3Service
{
    public function getDisk(MCSetupsLicense $license): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::build([
            'driver' => 's3',
            'key' => $license->s3_access_key,
            'secret' => $license->s3_secret_key,
            'region' => $license->s3_region ?: 'us-east-1',
            'bucket' => $license->s3_bucket,
            'endpoint' => $license->s3_endpoint,
            'use_path_style_endpoint' => true,
            'throw' => true,
        ]);
    }

    public function listAddons(MCSetupsLicense $license): array
    {
        $disk = $this->getDisk($license);
        $files = $disk->files('mcsetups-addons');
        $addons = [];
        foreach ($files as $f) {
            if (str_starts_with($f, 'mcsetups-addons/products/')) {
                continue;
            }
            if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'json') {
                continue;
            }
            $base = pathinfo($f, PATHINFO_FILENAME);
            $dir = dirname($f);
            $metaPath = $dir . '/' . $base . '.json';
            $meta = null;
            if ($disk->exists($metaPath)) {
                try {
                    $meta = json_decode($disk->get($metaPath), true) ?: null;
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            $displayName = $meta['display_name'] ?? $base;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $defaultFileType = null;
            if (empty($meta['file_type'])) {
                if ($ext === 'mcaddon') {
                    $defaultFileType = 'Add-on';
                } elseif ($ext === 'mcpack') {
                    $defaultFileType = 'Resource Pack';
                } elseif ($ext === 'mctemplate') {
                    $defaultFileType = 'Template';
                } elseif ($ext === 'zip' && (stripos($displayName, 'setup') !== false || stripos($base, 'setup') !== false)) {
                    $defaultFileType = 'Server Setup';
                }
            }
            $defaultFileLocation = ($defaultFileType === 'Server Setup') ? '/' : null;
            $addons[] = [
                'path' => $f,
                'name' => basename($f),
                'url' => $disk->url($f),
                'display_name' => $displayName,
                'file_type' => $meta['file_type'] ?? $defaultFileType,
                'file_location' => $meta['file_location'] ?? $defaultFileLocation,
                'placeholders' => $meta['placeholders'] ?? [],
                'product_link' => $meta['product_link'] ?? null,
                'cover_image_url' => $meta['cover_image_url'] ?? null,
                'source_name' => $meta['source_name'] ?? null,
                'price' => isset($meta['price']) ? (float) $meta['price'] : null,
            ];
        }
        return $addons;
    }

    public function listProducts(MCSetupsLicense $license): array
    {
        $disk = $this->getDisk($license);
        $files = $disk->files('mcsetups-addons/products');
        $products = [];
        foreach ($files as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if ($ext !== 'zip') {
                continue;
            }
            $dir = dirname($f);
            $base = pathinfo($f, PATHINFO_FILENAME);
            $metaPath = $dir . '/' . $base . '.json';
            $meta = null;
            if ($disk->exists($metaPath)) {
                try {
                    $meta = json_decode($disk->get($metaPath), true) ?: null;
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            $products[] = [
                'path' => $f,
                'name' => basename($f),
                'url' => $disk->url($f),
                'display_name' => $meta['display_name'] ?? $base,
                'description' => $meta['description'] ?? null,
                'game_version' => $meta['game_version'] ?? null,
                'category' => $meta['category'] ?? null,
                'author_name' => $meta['author_name'] ?? null,
                'placeholders' => $meta['placeholders'] ?? [],
                'required_addon_ids' => $meta['required_addon_ids'] ?? [],
                'optional_addon_ids' => $meta['optional_addon_ids'] ?? [],
                'product_link' => $meta['product_link'] ?? null,
                'cover_image_url' => $meta['cover_image_url'] ?? null,
                'source_name' => $meta['source_name'] ?? null,
                'price' => isset($meta['price']) ? (float) $meta['price'] : null,
            ];
        }
        return $products;
    }

    /** Update product meta only (rewrite .json). Path must be under mcsetups-addons/products/ and end with .zip etc. */
    public function updateProductMeta(MCSetupsLicense $license, string $path, array $meta): void
    {
        if (!str_starts_with($path, 'mcsetups-addons/products/')) {
            throw new \InvalidArgumentException('Invalid product path.');
        }
        $dir = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $disk = $this->getDisk($license);
        $disk->put($dir . '/' . $base . '.json', json_encode($meta, JSON_PRETTY_PRINT), 'public');
    }

    /** Upload new version of product (new file, keep or merge meta). */
    public function updateProductVersion(MCSetupsLicense $license, string $existingPath, string $newFilePath, array $meta): void
    {
        if (!str_starts_with($existingPath, 'mcsetups-addons/products/')) {
            throw new \InvalidArgumentException('Invalid product path.');
        }
        $disk = $this->getDisk($license);
        $dir = dirname($existingPath);
        $base = pathinfo($existingPath, PATHINFO_FILENAME);
        $disk->put($existingPath, file_get_contents($newFilePath), 'public');
        $disk->put($dir . '/' . $base . '.json', json_encode($meta, JSON_PRETTY_PRINT), 'public');
    }

    /** Update addon meta only (.json next to file). */
    public function updateAddonMeta(MCSetupsLicense $license, string $path, array $meta): void
    {
        if (!str_starts_with($path, 'mcsetups-addons/') || str_starts_with($path, 'mcsetups-addons/products/')) {
            throw new \InvalidArgumentException('Invalid addon path.');
        }
        $dir = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $disk = $this->getDisk($license);
        $disk->put($dir . '/' . $base . '.json', json_encode($meta, JSON_PRETTY_PRINT), 'public');
    }

    /** Replace addon file (new version), optionally update meta. */
    public function updateAddonVersion(MCSetupsLicense $license, string $existingPath, string $newFilePath, array $meta): void
    {
        if (!str_starts_with($existingPath, 'mcsetups-addons/') || str_starts_with($existingPath, 'mcsetups-addons/products/')) {
            throw new \InvalidArgumentException('Invalid addon path.');
        }
        $disk = $this->getDisk($license);
        $dir = dirname($existingPath);
        $base = pathinfo($existingPath, PATHINFO_FILENAME);
        $ext = pathinfo($existingPath, PATHINFO_EXTENSION);
        $disk->put($existingPath, file_get_contents($newFilePath), 'public');
        $disk->put($dir . '/' . $base . '.json', json_encode($meta, JSON_PRETTY_PRINT), 'public');
    }

    /**
     * Upload a cover image to S3 and return the public URL.
     * @param string $subdir e.g. 'products' or 'addons'
     * @param string $prefix optional prefix for filename (e.g. product base name)
     */
    public function uploadCoverImage(MCSetupsLicense $license, $uploadedFile, string $subdir = 'products', string $prefix = ''): string
    {
        $disk = $this->getDisk($license);
        $ext = strtolower($uploadedFile->getClientOriginalExtension() ?: 'jpg');
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        $safePrefix = $prefix !== '' ? preg_replace('/[^a-zA-Z0-9._-]/', '_', $prefix) . '_' : '';
        $filename = $safePrefix . 'cover_' . time() . '.' . $ext;
        $path = 'mcsetups-addons/covers/' . $subdir . '/' . $filename;
        $disk->put($path, file_get_contents($uploadedFile->getRealPath()), 'public');
        return $disk->url($path);
    }

    /** Delete addon file and its meta .json if present. */
    public function deleteAddon(MCSetupsLicense $license, string $path): void
    {
        if (!str_starts_with($path, 'mcsetups-addons/') || str_starts_with($path, 'mcsetups-addons/products/')) {
            throw new \InvalidArgumentException('Invalid addon path.');
        }
        $disk = $this->getDisk($license);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $dir = dirname($path);
        $disk->delete($path);
        $metaPath = $dir . '/' . $base . '.json';
        if ($disk->exists($metaPath)) {
            $disk->delete($metaPath);
        }
    }
}
