<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\MCSetupsLicense;
use Pterodactyl\Models\Egg;
use Pterodactyl\Services\Servers\MCSetups\MCSetupsS3Service;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\ReinstallServerService;

class MCSetupsController extends Controller
{
    private const MANUAL_LICENSE_SERVER_URL = 'https://mcapi.hxdev.org';
    private const MANUAL_LICENSE_SERVER_KEY = 'Unable to acquire a license key automatically, please contact the creator directly.';

    private function validateLicenseCredentials(string $storeUrl, string $licenseKey): bool
    {
        if (empty($storeUrl) || empty($licenseKey)) {
            return false;
        }

        try {
            $validateUrl = rtrim($storeUrl, '/') . '/store/validate';

            $response = Http::timeout(5)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                ])
                ->post($validateUrl, [
                    'license_key' => $licenseKey,
                ]);

            $responseJson = $response->json();

            if ($response->successful()) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getLicenseCredentials(bool $forceRefresh = false): array
    {
        $manualStoreUrl = trim(self::MANUAL_LICENSE_SERVER_URL);
        $manualLicenseKey = trim(self::MANUAL_LICENSE_SERVER_KEY);
        
        $dbLicense = MCSetupsLicense::where('is_active', true)->first();
        if (!$dbLicense) {
            $dbLicense = MCSetupsLicense::first();
        }
        $dbStoreUrl = $dbLicense ? $dbLicense->store_url : '';
        $dbLicenseKey = $dbLicense ? $dbLicense->license_key : '';
        
        $credentialsHash = md5($manualStoreUrl . ':' . $manualLicenseKey . ':' . $dbStoreUrl . ':' . $dbLicenseKey);
        $cacheKey = 'mcsetups:validated_credentials:' . $credentialsHash;
        
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }
        
        return Cache::remember($cacheKey, 60, function () use ($manualStoreUrl, $manualLicenseKey, $dbLicense) {
            if (!empty($manualStoreUrl) && !empty($manualLicenseKey)) {
                if ($this->validateLicenseCredentials($manualStoreUrl, $manualLicenseKey)) {
                    Log::info('MCSetups: Using manual license credentials', [
                        'store_url' => $manualStoreUrl,
                    ]);
                    return [
                        'store_url' => $manualStoreUrl,
                        'license_key' => $manualLicenseKey,
                    ];
                } else {
                    Log::warning('MCSetups: Manual license credentials validation failed', [
                        'store_url' => $manualStoreUrl,
                    ]);
                }
            }

            // Try database license
            if ($dbLicense) {
                if ($this->validateLicenseCredentials($dbLicense->store_url, $dbLicense->license_key)) {
                    Log::info('MCSetups: Using database license credentials', [
                        'store_url' => $dbLicense->store_url,
                    ]);
                    return [
                        'store_url' => $dbLicense->store_url,
                        'license_key' => $dbLicense->license_key,
                    ];
                } else {
                    Log::warning('MCSetups: Database license credentials validation failed', [
                        'store_url' => $dbLicense->store_url,
                    ]);
                    // If validation fails but we have credentials, return them anyway (might be temporary network issue)
                    // The install endpoint will handle validation
                    return [
                        'store_url' => $dbLicense->store_url,
                        'license_key' => $dbLicense->license_key,
                    ];
                }
            }

            Log::error('MCSetups: No valid license credentials found', [
                'has_manual_url' => !empty($manualStoreUrl),
                'has_manual_key' => !empty($manualLicenseKey),
                'has_db_license' => $dbLicense !== null,
            ]);

            return [
                'store_url' => '',
                'license_key' => '',
            ];
        });
    }

    public function index(Request $request, Server $server): JsonResponse
    {
        $forceRefresh = $request->input('force_refresh', false);
        $cacheKey = 'mcsetups:license:' . $server->uuid;
        
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }
        
        $license = Cache::remember($cacheKey, 60, function () {
            $activeLicense = MCSetupsLicense::where('is_active', true)->first();
            if (!$activeLicense) {
                $activeLicense = MCSetupsLicense::first();
            }
            return $activeLicense;
        });

        if ($license) {
            $license->makeVisible('license_key');
        }

        return new JsonResponse([
            'object' => 'mcsetups_license',
            'data' => $license,
        ]);
    }

    public function validateLicense(Request $request, Server $server): JsonResponse
    {

        $forceRefresh = $request->input('force_refresh', false);
        
        $credentials = $this->getLicenseCredentials($forceRefresh);
        $storeUrl = $credentials['store_url'];
        $licenseKey = $credentials['license_key'];
        
        
        if (!$storeUrl || !$licenseKey) {
            Log::error('MCSetups: No license configured at all');
            return new JsonResponse([
                'success' => false,
                'message' => 'No license configured.',
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $validated = [
            'store_url' => $storeUrl,
            'license_key' => $licenseKey,
        ];

        $storeUrl = rtrim($validated['store_url'], '/');
        $validateUrl = $storeUrl . '/store/validate';
        $cacheKey = 'mcsetups:validate_license:' . md5($storeUrl . ':' . $validated['license_key']);
        
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }
        
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult && !$forceRefresh) {
            return $cachedResult;
        }
        
        
        return Cache::remember($cacheKey, 30, function () use ($validated, $storeUrl, $validateUrl) {
            
            try {
                
                $response = Http::timeout(10)
                    ->withHeaders([
                        'X-License-Key' => $validated['license_key'],
                    ])
                    ->post($validateUrl, [
                        'license_key' => $validated['license_key'],
                    ]);
                

                $httpStatus = $response->status();
                $responseBody = $response->body();
                $responseJson = $response->json();

                if ($response->successful()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'License key is valid',
                    ]);
                }

                $errorMessage = 'Invalid license key';
                if (isset($responseJson['message'])) {
                    $errorMessage = $responseJson['message'];
                } elseif (isset($responseJson['reason'])) {
                    $errorMessage = $responseJson['reason'];
                } elseif (isset($responseJson['error'])) {
                    $errorMessage = is_array($responseJson['error']) 
                        ? ($responseJson['error']['message'] ?? $errorMessage)
                        : $responseJson['error'];
                }


                Log::error('MCSetups: License validation failed', [
                    'store_url' => $storeUrl,
                    'http_status' => $httpStatus,
                    'error_message' => $errorMessage,
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => $errorMessage,
                    'http_status' => $httpStatus,
                ], $httpStatus);
            } catch (\Exception $e) {
                Log::error('MCSetups: License validation exception', [
                    'error' => $e->getMessage(),
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'License validation failed: ' . $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        });
    }

    public function getStoreFiles(Request $request, Server $server): JsonResponse
    {
        $forceRefresh = $request->input('force_refresh', false);
        $credentials = $this->getLicenseCredentials($forceRefresh);
        $storeUrl = $credentials['store_url'];
        $licenseKey = $credentials['license_key'];
        
        if (!$storeUrl || !$licenseKey) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No active license configured. Please contact an administrator.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $cacheKey = 'mcsetups:store_files:' . md5($storeUrl . ':' . $licenseKey);
        
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }
        
        $cached = Cache::remember($cacheKey, 300, function () use ($storeUrl, $licenseKey) {
            try {
                $baseUrl = rtrim($storeUrl, '/');
                $apiUrl = $baseUrl . '/store/files';

                $maxRetries = 3;
                $retryDelay = 1;
                $response = null;
                $lastException = null;

                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    try {

                        $response = Http::timeout(15)
                            ->withHeaders([
                                'X-License-Key' => $licenseKey,
                            ])
                            ->get($apiUrl);

                        if ($response->status() === 429 && $attempt < $maxRetries) {
                            $retryAfter = $response->header('Retry-After') ?? $retryDelay;
                            $waitTime = (int) $retryAfter * (2 ** ($attempt - 1));
                            sleep($waitTime);
                            continue;
                        }

                        break;
                    } catch (\Exception $e) {
                        $lastException = $e;
                        if ($attempt < $maxRetries) {
                            $waitTime = $retryDelay * (2 ** ($attempt - 1));
                            sleep($waitTime);
                        }
                    }
                }

                if (!$response) {
                    throw $lastException ?? new \Exception('Failed to get response from store API after ' . $maxRetries . ' attempts');
                }

                $data = $response->json();
            
                if (isset($data['success']) && $data['success'] === false) {
                    $errorMessage = $data['error']['message'] ?? $data['error'] ?? $data['message'] ?? 'Failed to fetch store files';
                    Log::error('MCSetups get store files failed', [
                        'http_status' => $response->status(),
                        'error' => $errorMessage,
                        'response' => $data,
                    ]);
                    return ['success' => false, 'error' => $errorMessage, 'http_status' => $response->status()];
                }
                
                if (!$response->successful()) {
                    $errorData = $response->json();
                    $errorMessage = $errorData['error']['message'] ?? $errorData['error'] ?? 'Failed to fetch store files';
                    Log::error('MCSetups get store files failed', [
                        'http_status' => $response->status(),
                        'error' => $errorMessage,
                        'response' => $errorData,
                    ]);
                    return ['success' => false, 'error' => $errorMessage, 'http_status' => $response->status()];
                }
                
                $files = [];
                
                if (is_array($data)) {
                    if (isset($data['data']['files']) && is_array($data['data']['files'])) {
                        $files = $data['data']['files'];
                    } elseif (isset($data['files']) && is_array($data['files'])) {
                        $files = $data['files'];
                    } elseif (isset($data[0]) && is_array($data[0])) {
                        $files = $data;
                    } elseif (isset($data['data']) && is_array($data['data']) && isset($data['data'][0])) {
                        $files = $data['data'];
                    }
                } else {
                    Log::error('MCSetups: Response data is not an array', [
                        'data_type' => gettype($data),
                    ]);
                }
                
                return ['success' => true, 'files' => $files];
            } catch (\Exception $e) {
                Log::error('MCSetups get store files failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return ['success' => false, 'error' => 'Failed to fetch store files: ' . $e->getMessage()];
            }
        });

        if (isset($cached['success']) && $cached['success'] === false) {
            $status = $cached['http_status'] ?? Response::HTTP_INTERNAL_SERVER_ERROR;
            return new JsonResponse([
                'success' => false,
                'error' => $cached['error'] ?? 'Failed to fetch store files',
            ], $status);
        }

        $files = $cached['files'] ?? [];
        $baseUrl = rtrim($storeUrl, '/');
        $files = $this->normalizeCoverImageUrls($files, $baseUrl, $request, $server);

        $jsonResponse = new JsonResponse([
            'success' => true,
            'data' => ['files' => $files],
        ]);
        $jsonResponse->header('Cache-Control', 'private, max-age=300, stale-while-revalidate=60');
        return $jsonResponse;
    }

    /**
     * Normalize cover_image_url: make relative URLs absolute, proxy external images to avoid CORS/CORP blocking.
     */
    private function normalizeCoverImageUrls(array $files, string $storeBaseUrl, Request $request, Server $server): array
    {
        $proxyBase = $request->getSchemeAndHttpHost() . '/api/client/servers/' . $server->uuid . '/mcsetups/store/cover-image';

        foreach ($files as &$file) {
            $url = $file['cover_image_url'] ?? $file['cover_image'] ?? null;
            if (empty($url) || !is_string($url)) {
                continue;
            }
            if (str_starts_with($url, '/')) {
                $url = rtrim($storeBaseUrl, '/') . $url;
            }
            $parsed = parse_url($url);
            $imageHost = $parsed['host'] ?? '';
            $panelHost = $request->getHost();
            $isExternal = $imageHost !== '' && $imageHost !== $panelHost && !str_ends_with($imageHost, '.' . $panelHost);
            if ($isExternal || ($request->secure() && str_starts_with($url, 'http://'))) {
                $url = $proxyBase . '?url=' . rawurlencode($url);
            }
            $file['cover_image_url'] = $url;
            if (isset($file['cover_image'])) {
                $file['cover_image'] = $url;
            }
        }
        return $files;
    }

    /**
     * Proxy cover images to avoid mixed content (http images on https pages).
     */
    public function getCoverImage(Request $request, Server $server): Response
    {
        $url = $request->query('url');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new Response('Invalid URL', 400);
        }
        $parsed = parse_url($url);
        $allowedHosts = ['localhost', '127.0.0.1', 'network.hxdev.org', 'mcapi.hxdev.org', '46.202.166.42'];
        $host = $parsed['host'] ?? '';
        if (!in_array($host, $allowedHosts, true) && !str_ends_with($host, '.hxdev.org')) {
            return new Response('URL not allowed', 403);
        }
        try {
            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) {
                return new Response('Image not found', 404);
            }
            $contentType = $response->header('Content-Type') ?: 'image/jpeg';
            return new Response($response->body(), 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\Exception $e) {
            Log::warning('MCSetups cover image proxy failed', ['url' => $url, 'error' => $e->getMessage()]);
            return new Response('Failed to fetch image', 502);
        }
    }

    public function getStoreFilters(Request $request, Server $server): JsonResponse
    {
        $credentials = $this->getLicenseCredentials(false);
        $storeUrl = $credentials['store_url'];

        if (!$storeUrl) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No active license configured. Please contact an administrator.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $license = MCSetupsLicense::first();
        $storeCacheKey = 'mcsetups:store_filters:' . md5($storeUrl);

        try {
            $storeFilters = Cache::remember($storeCacheKey, 60, function () use ($storeUrl) {
                $baseUrl = rtrim($storeUrl, '/');
                $apiUrl = $baseUrl . '/store/filters';

                try {
                    $response = Http::timeout(10)->get($apiUrl);
                    if (!$response->successful()) {
                        Log::warning('MCSetups get store filters failed', [
                            'store_url' => $storeUrl,
                            'http_status' => $response->status(),
                        ]);
                        return ['game_versions' => [], 'categories' => []];
                    }
                    $data = $response->json();
                    $gameVersions = $data['data']['game_versions'] ?? [];
                    $categories = $data['data']['categories'] ?? [];
                    return [
                        'game_versions' => is_array($gameVersions) ? $gameVersions : [],
                        'categories' => is_array($categories) ? $categories : [],
                    ];
                } catch (\Exception $e) {
                    Log::warning('MCSetups get store filters request failed', ['error' => $e->getMessage()]);
                    return ['game_versions' => [], 'categories' => []];
                }
            });

            $gameVersions = $storeFilters['game_versions'];
            $categories = $storeFilters['categories'];

            if ($license && $license->hasS3Config()) {
                $s3Versions = [];
                $s3Categories = [];
                try {
                    $s3Service = app(MCSetupsS3Service::class);
                    $products = $s3Service->listProducts($license);
                    foreach ($products as $p) {
                        $v = isset($p['game_version']) ? trim((string) $p['game_version']) : '';
                        if ($v !== '') {
                            $s3Versions[$v] = true;
                        }
                        $c = isset($p['category']) ? trim((string) $p['category']) : '';
                        if ($c !== '') {
                            $s3Categories[$c] = true;
                        }
                    }
                    $gameVersions = array_values(array_unique(array_merge($gameVersions, array_keys($s3Versions))));
                    sort($gameVersions);
                    $categories = array_values(array_unique(array_merge($categories, array_keys($s3Categories))));
                    sort($categories);
                } catch (\Exception $e) {
                    Log::warning('MCSetups merge S3 filters failed', ['error' => $e->getMessage()]);
                }
            }

            $result = [
                'success' => true,
                'data' => [
                    'game_versions' => $gameVersions,
                    'categories' => $categories,
                ],
            ];

            $jsonResponse = new JsonResponse($result);
            $jsonResponse->header('Cache-Control', 'private, max-age=60, stale-while-revalidate=300');
            return $jsonResponse;
        } catch (\Exception $e) {
            Log::error('MCSetups get store filters failed', [
                'store_url' => $storeUrl,
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse([
                'success' => false,
                'error' => 'Store server is temporarily unavailable. Try again later.',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    public function getStoreFile(Request $request, Server $server, string $fileId): JsonResponse
    {
        $fileId = trim($fileId);
        if ($fileId === '') {
            return new JsonResponse([
                'success' => false,
                'error' => 'File ID is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $credentials = $this->getLicenseCredentials(false);
        $storeUrl = $credentials['store_url'];
        $licenseKey = $credentials['license_key'];
        
        if (!$storeUrl || !$licenseKey) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No active license configured. Please contact an administrator.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $cacheKey = 'mcsetups:store_file:' . md5($storeUrl . ':' . $licenseKey . ':' . $fileId);
        $baseUrl = rtrim($storeUrl, '/');
        
        $cached = Cache::remember($cacheKey, 600, function () use ($baseUrl, $licenseKey, $fileId) {
            try {
                $apiUrl = $baseUrl . '/store/files/' . $fileId;
                $response = Http::timeout(15)
                    ->withHeaders(['X-License-Key' => $licenseKey])
                    ->get($apiUrl);
                $data = $response->json();
                if (!isset($data['success'])) {
                    $data['success'] = true;
                }
                return $data;
            } catch (\Exception $e) {
                Log::error('MCSetups get store file failed', [
                    'file_id' => $fileId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return ['success' => false, 'error' => 'Failed to fetch store file: ' . $e->getMessage()];
            }
        });

        if (isset($cached['success']) && $cached['success'] === false) {
            return new JsonResponse($cached, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $file = $cached['data'] ?? $cached;
        if (is_array($file)) {
            $normalized = $this->normalizeCoverImageUrls([$file], $baseUrl, $request, $server);
            $file = $normalized[0] ?? $file;
        }
        if (isset($cached['data'])) {
            $cached['data'] = $file;
        } else {
            $cached = $file;
        }

        $jsonResponse = new JsonResponse($cached);
        $jsonResponse->header('Cache-Control', 'private, max-age=600, stale-while-revalidate=120');
        return $jsonResponse;
    }

    public function install(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'file_id' => 'required_without:fileId',
            'fileId' => 'required_without:file_id',
            'placeholder_values' => 'nullable|array',
            'addon_ids' => 'nullable|array',
            'wipe_data' => 'nullable|boolean',
            'zip_and_wipe' => 'nullable|boolean',
            'store_url' => 'nullable|string',
            'license_key' => 'nullable|string',
        ]);

        $fileIdInput = $validated['file_id'] ?? $validated['fileId'] ?? null;
        $fileIdInput = is_string($fileIdInput) ? trim($fileIdInput) : (string) $fileIdInput;
        if ($fileIdInput === '') {
            return new JsonResponse([
                'error' => 'The file id is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (is_numeric($fileIdInput)) {
            $validated['file_id'] = (int) $fileIdInput;
        } else {
            $validated['file_id'] = $fileIdInput;
        }

        $validated['wipe_data'] = (bool) ($validated['wipe_data'] ?? false);
        $validated['zip_and_wipe'] = (bool) ($validated['zip_and_wipe'] ?? false);
        $validated['placeholder_values'] = $validated['placeholder_values'] ?? [];
        $validated['addon_ids'] = $validated['addon_ids'] ?? [];

        $credentials = $this->getLicenseCredentials(false);
        $storeUrl = $credentials['store_url'];
        $licenseKey = $credentials['license_key'];
        
        if (!$storeUrl || !$licenseKey) {
            return new JsonResponse([
                'error' => 'No active license configured. Please contact an administrator.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $installationEgg = Egg::where('name', 'MCSetups Installer')
                ->where('author', 'support@hxdev.org')
                ->firstOrFail();
            
            $mcsetupsEggJson = json_decode(file_get_contents(base_path('database/Seeders/eggs/egg-mcsetups-installer.json')), true);
            $mcsetupsInstallScript = $mcsetupsEggJson['scripts']['installation']['script'] ?? '';
            $mcsetupsScriptContainer = $mcsetupsEggJson['scripts']['installation']['container'] ?? 'ghcr.io/pterodactyl/installers:debian';
            $mcsetupsScriptEntry = $mcsetupsEggJson['scripts']['installation']['entrypoint'] ?? 'bash';
            
            $originalScript = $installationEgg->script_install;
            $originalScriptContainer = $installationEgg->script_container;
            $originalScriptEntry = $installationEgg->script_entry;

            if ($server->egg_id === $installationEgg->id) {
                if ($server->status === Server::STATUS_INSTALLING) {
                    throw new \Exception('A setup installation is already in progress for this server.');
                } else {
                    $originalEgg = Egg::where('id', '!=', $installationEgg->id)
                        ->where('nest_id', $server->nest_id)
                        ->first();
                    if ($originalEgg) {
                        $server->forceFill([
                            'nest_id' => $originalEgg->nest_id,
                            'egg_id' => $originalEgg->id,
                            'status' => null,
                        ])->save();
                        $server->refresh();
                    }
                }
            }

            $powerRepository = app(\Pterodactyl\Repositories\Wings\DaemonPowerRepository::class);
            try {
                $powerRepository->setServer($server)->send('kill');
            } catch (\Exception $e) {
            }
            
            usleep(500000);

            $originalEgg = $server->egg;
            
            $installationEgg->forceFill([
                'script_install' => $mcsetupsInstallScript,
                'script_container' => $mcsetupsScriptContainer,
                'script_entry' => $mcsetupsScriptEntry,
            ])->save();

            $server->forceFill([
                'nest_id' => $installationEgg->nest_id,
                'egg_id' => $installationEgg->id,
                'status' => Server::STATUS_INSTALLING,
                'installed_at' => null,
            ])->save();

            $server->refresh();

            $addonDetails = [];
            if (!empty($validated['addon_ids'])) {
                try {
                    $baseUrl = rtrim($storeUrl, '/');
                    $storeFileUrl = $baseUrl . '/store/files/' . $validated['file_id'];
                    
                    $response = Http::timeout(30)
                        ->withHeaders([
                            'X-License-Key' => $licenseKey,
                        ])
                        ->get($storeFileUrl);

                    if ($response->successful()) {
                        $fileData = $response->json();
                        $allAddons = $fileData['data']['addons'] ?? [];
                        
                        foreach ($allAddons as $addon) {
                            if (in_array($addon['id'], $validated['addon_ids'])) {
                                $addonDetails[] = [
                                    'id' => $addon['id'],
                                    'file_location' => $addon['file_location'] ?? '/',
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            $installationEggVariables = $installationEgg->variables()->get();
            $existingVariables = $server->variables()->get();
            $environment = [];
            
            foreach ($installationEggVariables as $eggVar) {
                $existingVar = $existingVariables->firstWhere('env_variable', $eggVar->env_variable);
                if ($existingVar) {
                    $environment[$eggVar->env_variable] = $existingVar->server_value ?? $existingVar->default_value ?? $eggVar->default_value ?? '';
                } else {
                    $environment[$eggVar->env_variable] = $eggVar->default_value ?? '';
                }
                
                if (str_contains($eggVar->rules, 'required') && empty($environment[$eggVar->env_variable])) {
                    if (str_contains(strtolower($eggVar->name), 'version')) {
                        $environment[$eggVar->env_variable] = 'latest';
                    } elseif (str_contains(strtolower($eggVar->name), 'jarfile') || str_contains(strtolower($eggVar->env_variable), 'JARFILE')) {
                        $environment[$eggVar->env_variable] = 'server.jar';
                    } elseif (str_contains(strtolower($eggVar->name), 'build')) {
                        $environment[$eggVar->env_variable] = 'latest';
                    } else {
                        $environment[$eggVar->env_variable] = $eggVar->default_value ?? '';
                    }
                }
            }
            
            $environment['MCSETUPS_STORE_URL'] = $storeUrl;
            $environment['MCSETUPS_LICENSE_KEY'] = $licenseKey;
            $environment['MCSETUPS_FILE_ID'] = (string) $validated['file_id'];
            $environment['MCSETUPS_PLACEHOLDER_VALUES'] = json_encode($validated['placeholder_values'] ?? []);
            $environment['MCSETUPS_ADDON_IDS'] = json_encode($validated['addon_ids'] ?? []);
            $environment['MCSETUPS_ADDON_DETAILS'] = json_encode($addonDetails);
            $environment['MCSETUPS_WIPE_DATA'] = $validated['wipe_data'] ? 'true' : 'false';
            $environment['MCSETUPS_ZIP_AND_WIPE'] = $validated['zip_and_wipe'] ? 'true' : 'false';
            
            if (empty($environment['SERVER_JARFILE'])) {
                $environment['SERVER_JARFILE'] = 'server.jar';
            }

            $startupModificationService = app(\Pterodactyl\Services\Servers\StartupModificationService::class);
            $startupModificationService->setUserLevel(\Pterodactyl\Models\User::USER_LEVEL_ADMIN);
            $startupModificationService->handle($server, [
                'environment' => $environment,
            ]);

            $server->refresh();

            $daemonServerRepository = app(\Pterodactyl\Repositories\Wings\DaemonServerRepository::class);
            $daemonServerRepository->setServer($server)->sync();

            $reinstallService = app(ReinstallServerService::class);
            $reinstallService->handle($server);

            \Pterodactyl\Jobs\Server\InstallSetupJob::dispatch($server, $originalEgg);

            return new JsonResponse([
                'success' => true,
                'message' => 'Setup installation started successfully.',
            ]);
        } catch (\Exception $e) {
            if (isset($installationEgg) && isset($originalScript)) {
                try {
                    $installationEgg->forceFill([
                        'script_install' => $originalScript,
                        'script_container' => $originalScriptContainer,
                        'script_entry' => $originalScriptEntry,
                    ])->save();
                } catch (\Exception $cleanupException) {
                }
            }

            Log::error('MCSetups installation failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changeEgg(Request $request, Server $server): JsonResponse
    {
        try {
            $installationEgg = Egg::where('name', 'MCSetups Installer')
                ->where('author', 'support@hxdev.org')
                ->firstOrFail();

            if ($server->egg_id !== $installationEgg->id) {
                $server->forceFill([
                    'nest_id' => $installationEgg->nest_id,
                    'egg_id' => $installationEgg->id,
                ])->save();
                $server->refresh();

                if ($server->egg_id !== $installationEgg->id) {
                    Log::error('MCSetups: Egg was not changed correctly', [
                        'server_id' => $server->id,
                        'expected_egg_id' => $installationEgg->id,
                        'actual_egg_id' => $server->egg_id,
                    ]);
                }
            }

            return new JsonResponse([
                'success' => true,
                'egg_id' => $server->egg_id,
            ]);
        } catch (\Exception $e) {
            Log::error('MCSetups: Failed to change server egg', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function testDownload(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
        ]);

        try {
            $response = Http::timeout(30)->get($validated['url']);

            if ($response->successful()) {
                return new JsonResponse([
                    'success' => true,
                    'size' => strlen($response->body()),
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'error' => 'Download failed with status: ' . $response->status(),
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('MCSetups download test failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateStartupCommand(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'startup_command' => 'required|string|max:500',
        ]);

        try {
            $oldStartup = $server->startup;
            $newStartup = $validated['startup_command'];

            $server->forceFill(['startup' => $newStartup])->save();
            $server->refresh();

            if ($server->startup !== $newStartup) {
                $server->forceFill(['startup' => $newStartup])->save();
                $server->refresh();
            }

            $daemonServerRepository = app(DaemonServerRepository::class);
            $daemonServerRepository->setServer($server)->sync();

            $server->refresh();

            if ($server->startup !== $newStartup) {
                $server->forceFill(['startup' => $newStartup])->save();
                $server->refresh();
                $daemonServerRepository->setServer($server)->sync();
            }

            return new JsonResponse([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('MCSetups: Failed to update startup command', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resetStuckInstallation(Request $request, Server $server): JsonResponse
    {
        try {
            $installationEgg = Egg::where('name', 'MCSetups Installer')
                ->where('author', 'support@hxdev.org')
                ->first();

            if ($installationEgg && $server->egg_id === $installationEgg->id) {
                $originalEgg = Egg::where('id', '!=', $installationEgg->id)
                    ->where('nest_id', $server->nest_id)
                    ->first();

                if ($originalEgg) {
                    $server->forceFill([
                        'nest_id' => $originalEgg->nest_id,
                        'egg_id' => $originalEgg->id,
                        'status' => null,
                        'installed_at' => now(),
                    ])->save();

                    $server->refresh();

                    $daemonServerRepository = app(DaemonServerRepository::class);
                    $daemonServerRepository->setServer($server)->sync();

                    Log::info('MCSetups: Reset stuck installation - restored original egg', [
                        'server_id' => $server->id,
                        'original_egg_id' => $originalEgg->id,
                    ]);
                } else {
                    $server->forceFill([
                        'status' => null,
                        'installed_at' => now(),
                    ])->save();

                    Log::warning('MCSetups: Reset stuck installation - cleared status but could not find original egg', [
                        'server_id' => $server->id,
                    ]);
                }
            } else {
                if ($server->status === Server::STATUS_INSTALLING) {
                    $server->forceFill([
                        'status' => null,
                        'installed_at' => now(),
                    ])->save();

                    Log::info('MCSetups: Reset stuck installation - cleared status', [
                        'server_id' => $server->id,
                    ]);
                }
            }

            $server->refresh();

            return new JsonResponse([
                'success' => true,
                'message' => 'Stuck installation has been reset.',
                'server_status' => $server->status,
                'egg_id' => $server->egg_id,
            ]);
        } catch (\Exception $e) {
            Log::error('MCSetups: Failed to reset stuck installation', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to reset stuck installation: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUploadedAddons(Request $request, Server $server): JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->hasS3Config()) {
            return new JsonResponse([
                'success' => true,
                'data' => ['addons' => []],
            ]);
        }

        try {
            $s3Service = app(MCSetupsS3Service::class);
            $addons = $s3Service->listAddons($license);
            return new JsonResponse([
                'success' => true,
                'data' => ['addons' => $addons],
            ]);
        } catch (\Exception $e) {
            Log::error('MCSetups: Failed to list uploaded addons', ['error' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUploadedAddonDownloadUrl(Request $request, Server $server, string $filename): JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->hasS3Config()) {
            return new JsonResponse(['success' => false, 'error' => 'S3 not configured'], Response::HTTP_BAD_REQUEST);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));
        $path = 'mcsetups-addons/' . $safeName;

        try {
            $s3Service = app(MCSetupsS3Service::class);
            $disk = $s3Service->getDisk($license);
            if (!$disk->exists($path)) {
                return new JsonResponse(['success' => false, 'error' => 'Addon not found'], Response::HTTP_NOT_FOUND);
            }
            $url = $disk->url($path);
            return new JsonResponse([
                'success' => true,
                'data' => ['url' => $url, 'path' => $path],
            ]);
        } catch (\Exception $e) {
            Log::error('MCSetups: Failed to get addon download URL', ['error' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
