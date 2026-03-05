<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\MCSetupsLicense;
use Pterodactyl\Services\Servers\MCSetups\UrlResolverService;
use Pterodactyl\Services\Servers\MCSetups\MCSetupsS3Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MCSetupsController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private UrlResolverService $urlResolver,
        private MCSetupsS3Service $s3Service
    ) {
    }

    public function index(): View
    {
        $license = MCSetupsLicense::first();

        return view('admin.mcsetups.index', [
            'license' => $license,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|max:255',
            'store_url' => 'required|string',
            's3_endpoint' => 'nullable|string|max:512',
            's3_access_key' => 'nullable|string|max:512',
            's3_secret_key' => 'nullable|string|max:512',
            's3_bucket' => 'nullable|string|max:255',
            's3_region' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $baseUrl = rtrim($validated['store_url'], '/');
        if (!preg_match('/^https?:\/\//', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
        
        // Resolve shortened URLs (e.g., ptero.co)
        $resolvedUrl = $this->urlResolver->resolveUrl($baseUrl);
        
        // Extract base URL from resolved URL (in case it was a Pterodactyl Panel URL)
        $extractedBaseUrl = $this->urlResolver->extractStoreUrlFromPterodactylUrl($resolvedUrl);
        if ($extractedBaseUrl) {
            $baseUrl = $extractedBaseUrl;
        } else {
            $baseUrl = rtrim($resolvedUrl, '/');
        }
        
        $storeUrl = $baseUrl . '/store/validate';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($storeUrl, [
                'license_key' => $validated['license_key'],
            ]);

            $statusCode = $response->status();
            $responseBody = $response->body();
            $responseJson = $response->json();

            Log::info('MCSetups license validation request', [
                'store_url' => $storeUrl,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'response_json' => $responseJson,
            ]);

            if (!$response->successful() || ($responseJson['success'] ?? false) !== true) {
                Log::warning('MCSetups license validation failed', [
                    'store_url' => $storeUrl,
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'response_json' => $responseJson,
                ]);

                $errorMessage = 'Invalid license key. Please verify the license key is correct.';
                if ($statusCode >= 500) {
                    $errorMessage = 'Store server error. Please try again later.';
                } elseif ($statusCode === 404) {
                    $errorMessage = 'Store endpoint not found. Please verify the store URL is correct.';
                } elseif ($statusCode === 0 || !$response->successful()) {
                    $errorMessage = 'Unable to connect to store. Please verify the store URL is correct and accessible.';
                }

                $this->alert->danger($errorMessage)->flash();
                return redirect()->back()->withInput();
            }

            $license = MCSetupsLicense::updateOrCreate(
                [],
                [
                    'license_key' => $validated['license_key'],
                    'store_url' => $baseUrl,
                    's3_endpoint' => $validated['s3_endpoint'] ?? null,
                    's3_access_key' => $validated['s3_access_key'] ?? null,
                    's3_secret_key' => $validated['s3_secret_key'] ?? null,
                    's3_bucket' => $validated['s3_bucket'] ?? null,
                    's3_region' => $validated['s3_region'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                    'expires_at' => $validated['expires_at'] ?? null,
                ]
            );

            $this->clearLicenseCaches($baseUrl, $validated['license_key']);

            $this->alert->success('License key created successfully.')->flash();
        } catch (\Exception $e) {
            Log::error('MCSetups admin license creation failed', [
                'error' => $e->getMessage(),
            ]);

            $this->alert->danger('Failed to validate license key.')->flash();
        }

        return redirect()->route('admin.mcsetups');
    }

    public function update(Request $request): RedirectResponse
    {
        $license = MCSetupsLicense::firstOrFail();

        $validated = $request->validate([
            'license_key' => 'required|string|max:255',
            'store_url' => 'required|string',
            's3_endpoint' => 'nullable|string|max:512',
            's3_access_key' => 'nullable|string|max:512',
            's3_secret_key' => 'nullable|string|max:512',
            's3_bucket' => 'nullable|string|max:255',
            's3_region' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $baseUrl = rtrim($validated['store_url'], '/');
        if (!preg_match('/^https?:\/\//', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }
        
        // Resolve shortened URLs (e.g., ptero.co)
        $resolvedUrl = $this->urlResolver->resolveUrl($baseUrl);
        
        // Extract base URL from resolved URL (in case it was a Pterodactyl Panel URL)
        $extractedBaseUrl = $this->urlResolver->extractStoreUrlFromPterodactylUrl($resolvedUrl);
        if ($extractedBaseUrl) {
            $baseUrl = $extractedBaseUrl;
        } else {
            $baseUrl = rtrim($resolvedUrl, '/');
        }
        
        $storeUrl = $baseUrl . '/store/validate';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($storeUrl, [
                'license_key' => $validated['license_key'],
            ]);

            $statusCode = $response->status();
            $responseBody = $response->body();
            $responseJson = $response->json();

            Log::info('MCSetups license validation request (update)', [
                'store_url' => $storeUrl,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'response_json' => $responseJson,
            ]);

            if (!$response->successful() || ($responseJson['success'] ?? false) !== true) {
                Log::warning('MCSetups license validation failed (update)', [
                    'store_url' => $storeUrl,
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'response_json' => $responseJson,
                ]);

                $errorMessage = 'Invalid license key. Please verify the license key is correct.';
                if ($statusCode >= 500) {
                    $errorMessage = 'Store server error. Please try again later.';
                } elseif ($statusCode === 404) {
                    $errorMessage = 'Store endpoint not found. Please verify the store URL is correct.';
                } elseif ($statusCode === 0 || !$response->successful()) {
                    $errorMessage = 'Unable to connect to store. Please verify the store URL is correct and accessible.';
                }

                $this->alert->danger($errorMessage)->flash();
                return redirect()->back();
            }

            $oldStoreUrl = $license->store_url;
            $oldLicenseKey = $license->license_key;

            $update = [
                'license_key' => $validated['license_key'],
                'store_url' => $baseUrl,
                's3_endpoint' => $validated['s3_endpoint'] ?? $license->s3_endpoint,
                's3_bucket' => $validated['s3_bucket'] ?? $license->s3_bucket,
                's3_region' => $validated['s3_region'] ?? $license->s3_region,
                'is_active' => $validated['is_active'] ?? $license->is_active,
                'expires_at' => $validated['expires_at'] ?? $license->expires_at,
            ];
            if (!empty(trim((string) ($validated['s3_access_key'] ?? '')))) {
                $update['s3_access_key'] = $validated['s3_access_key'];
            }
            if (!empty(trim((string) ($validated['s3_secret_key'] ?? '')))) {
                $update['s3_secret_key'] = $validated['s3_secret_key'];
            }
            $license->fill($update)->save();

            $this->clearLicenseCaches($oldStoreUrl, $oldLicenseKey);
            $this->clearLicenseCaches($baseUrl, $validated['license_key']);

            $this->alert->success('License key updated successfully.')->flash();
        } catch (\Exception $e) {
            Log::error('MCSetups admin license update failed', [
                'error' => $e->getMessage(),
            ]);

            $this->alert->danger('Failed to validate license key.')->flash();
        }

        return redirect()->route('admin.mcsetups');
    }

    public function delete(): RedirectResponse
    {
        $license = MCSetupsLicense::firstOrFail();
        $storeUrl = $license->store_url;
        $licenseKey = $license->license_key;

        $license->delete();

        $this->clearLicenseCaches($storeUrl, $licenseKey);

        $this->alert->success('License key deleted successfully.')->flash();

        return redirect()->route('admin.mcsetups');
    }

    public function uploadAddon(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        set_time_limit(300);
        $license = MCSetupsLicense::firstOrFail();
        if (!$license->hasS3Config()) {
            $msg = 'S3 storage is not configured. Configure it in the license form above.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'error' => $msg], 400);
            }
            $this->alert->danger($msg)->flash();
            return redirect()->route('admin.mcsetups');
        }

        $validated = $request->validate([
            'addon_file' => [
                'required',
                'file',
                'max:512000',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['zip', 'mcaddon', 'mcpack', 'mctemplate'])) {
                        $fail('File must be .zip, .mcaddon, .mcpack, or .mctemplate.');
                    }
                },
            ],
            'display_name' => 'nullable|string|max:255',
            'file_type' => 'nullable|string|max:128',
            'file_location' => 'nullable|string|max:255',
            'placeholders' => 'nullable|string|max:10000',
            'product_link' => 'nullable|string|max:1024',
            'cover_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'source_name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
        ]);

        $file = $request->file('addon_file');
        $originalName = $file->getClientOriginalName();
        $displayName = trim((string) ($validated['display_name'] ?? '')) ?: pathinfo($originalName, PATHINFO_FILENAME);
        $ext = $file->getClientOriginalExtension() ?: 'zip';
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $displayName) . '_' . time() . '.' . $ext;
        $path = 'mcsetups-addons/' . $safeName;

        $placeholders = [];
        if (!empty($validated['placeholders'])) {
            $decoded = json_decode($validated['placeholders'], true);
            if (is_array($decoded)) {
                $placeholders = $decoded;
            }
        }

        $coverUrl = null;
        if ($request->hasFile('cover_image')) {
            $coverUrl = $this->s3Service->uploadCoverImage($license, $request->file('cover_image'), 'addons', $displayName);
        }

        $meta = [
            'display_name' => $displayName,
            'file_type' => trim((string) ($validated['file_type'] ?? '')) ?: null,
            'file_location' => trim((string) ($validated['file_location'] ?? '')) ?: null,
            'placeholders' => $placeholders,
            'product_link' => trim((string) ($validated['product_link'] ?? '')) ?: null,
            'cover_image_url' => $coverUrl,
            'source_name' => trim((string) ($validated['source_name'] ?? '')) ?: null,
            'price' => isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null ? (float) $validated['price'] : null,
        ];

        try {
            $disk = $this->s3Service->getDisk($license);
            $disk->put($path, file_get_contents($file->getRealPath()), 'public');
            $base = pathinfo($path, PATHINFO_FILENAME);
            $disk->put('mcsetups-addons/' . $base . '.json', json_encode($meta, JSON_PRETTY_PRINT), 'public');

            Cache::forget('mcsetups:list_addons');

            Log::info('MCSetups addon uploaded to S3', ['path' => $path, 'display_name' => $displayName]);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => "Add-on uploaded: {$displayName}."]);
            }
            $this->alert->success("Add-on uploaded: {$displayName}. Stored at {$path}")->flash();
        } catch (\Exception $e) {
            Log::error('MCSetups addon upload failed', ['error' => $e->getMessage()]);
            if ($request->ajax()) {
                return response()->json(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()], 500);
            }
            $this->alert->danger('Upload failed: ' . $e->getMessage())->flash();
        }

        return redirect()->route('admin.mcsetups');
    }

    public function listUploadedAddons(Request $request): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->hasS3Config()) {
            return response()->json(['success' => true, 'data' => ['addons' => []]]);
        }

        try {
            $addons = $request->query('fresh')
                ? $this->s3Service->listAddons($license)
                : Cache::remember('mcsetups:list_addons', 30, fn () => $this->s3Service->listAddons($license));
            return response()->json(['success' => true, 'data' => ['addons' => $addons]]);
        } catch (\Exception $e) {
            Log::error('MCSetups list addons failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function listUploadedProducts(): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->hasS3Config()) {
            return response()->json(['success' => true, 'data' => ['products' => []]]);
        }

        try {
            $products = Cache::remember('mcsetups:list_products', 30, fn () => $this->s3Service->listProducts($license));
            return response()->json(['success' => true, 'data' => ['products' => $products]]);
        } catch (\Exception $e) {
            Log::error('MCSetups list products failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Proxy to store's /client/products to avoid CORS. Lists products the license has uploaded (not store catalog).
     */
    public function proxyClientProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/products';
        if ($request->query('license_key')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'license_key=' . urlencode($request->query('license_key'));
        }
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key, 'Accept' => 'application/json'])
                ->get($url);
            $data = $response->json() ?? ['success' => false, 'error' => 'Invalid response'];
            if ($response->successful() && isset($data['data'])) {
                $key = isset($data['data']['files']) ? 'files' : (isset($data['data']['products']) ? 'products' : null);
                if ($key && is_array($data['data'][$key])) {
                    foreach ($data['data'][$key] as &$file) {
                        if (!isset($file['id'])) {
                            $resolved = $file['file_id'] ?? $file['product_id'] ?? $file['fileId'] ?? $file['productId'] ?? $file['_id'] ?? null;
                            if ($resolved !== null) {
                                $file['id'] = $resolved;
                            }
                        }
                    }
                    unset($file);
                    if ($key === 'products') {
                        $data['data']['files'] = $data['data']['products'];
                    }
                }
            }
            return response()->json($data, $response->status());
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy client products failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    public function proxyClientProduct(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $id = preg_replace('/^client-/', '', $id);
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/products/' . $id;
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key, 'Accept' => 'application/json'])
                ->get($url);
            return response()->json($response->json() ?? ['success' => false, 'error' => 'Invalid response'], $response->status());
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy client product failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Proxy to store's /client/addons to avoid CORS when fetching from admin panel.
     */
    public function proxyClientAddons(Request $request): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/addons';
        if ($request->query('license_key')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'license_key=' . urlencode($request->query('license_key'));
        }
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key, 'Accept' => 'application/json'])
                ->get($url);
            $data = $response->json() ?? ['success' => false, 'error' => 'Invalid response'];
            if ($response->successful() && isset($data['data'])) {
                $key = isset($data['data']['addons']) ? 'addons' : (isset($data['data']['files']) ? 'files' : null);
                if ($key && is_array($data['data'][$key])) {
                    foreach ($data['data'][$key] as &$item) {
                        if (!isset($item['id'])) {
                            $resolved = $item['file_id'] ?? $item['addon_id'] ?? $item['fileId'] ?? $item['addonId'] ?? $item['_id'] ?? null;
                            if ($resolved !== null) {
                                $item['id'] = $resolved;
                            }
                        }
                    }
                    unset($item);
                }
            }
            return response()->json($data, $response->status());
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy client addons failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Proxy to store's /client/addons/:id to avoid CORS.
     */
    public function proxyClientAddon(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $id = preg_replace('/^client-/', '', $id);
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/addons/' . $id;
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key, 'Accept' => 'application/json'])
                ->get($url);
            return response()->json($response->json() ?? ['success' => false, 'error' => 'Invalid response'], $response->status());
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy client addon failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Proxy DELETE to store's /client/products/:id to avoid CORS.
     */
    public function proxyClientProductDelete(string $id): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $id = preg_replace('/^client-/', '', $id);
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/products/' . $id;
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key, 'Accept' => 'application/json'])
                ->delete($url);
            return response()->json($response->json() ?? ['success' => false, 'error' => 'Invalid response'], $response->status());
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy client product DELETE failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Proxy DELETE to store's /client/addons/:id to avoid CORS.
     */
    public function proxyClientAddonDelete(string $id): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $id = preg_replace('/^client-/', '', $id);
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/addons/' . $id;
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key, 'Accept' => 'application/json'])
                ->delete($url);
            return response()->json($response->json() ?? ['success' => false, 'error' => 'Invalid response'], $response->status());
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy client addon DELETE failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Proxy PATCH to store's /client/products/:id to avoid CORS.
     */
    public function proxyClientProductPatch(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $id = preg_replace('/^client-/', '', $id);
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/products/' . $id;
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key, 'Accept' => 'application/json', 'Content-Type' => 'application/json'])
                ->withBody($request->getContent(), 'application/json')
                ->patch($url);
            return response()->json($response->json() ?? ['success' => false, 'error' => 'Invalid response'], $response->status());
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy client product PATCH failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Proxy PATCH to store's /client/addons/:id to avoid CORS.
     */
    public function proxyClientAddonPatch(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $id = preg_replace('/^client-/', '', $id);
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/addons/' . $id;
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-License-Key' => $license->license_key, 'Accept' => 'application/json', 'Content-Type' => 'application/json'])
                ->withBody($request->getContent(), 'application/json')
                ->patch($url);
            return response()->json($response->json() ?? ['success' => false, 'error' => 'Invalid response'], $response->status());
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy client addon PATCH failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Proxy POST to store's /client/products (upload) to avoid CORS.
     */
    public function proxyClientProductsPost(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/products';
        return $this->proxyMultipartPost($request, $url, $license->license_key);
    }

    /**
     * Proxy POST to store's /client/addons (upload) to avoid CORS.
     */
    public function proxyClientAddonsPost(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
    {
        $license = MCSetupsLicense::first();
        if (!$license || !$license->store_url || !$license->license_key) {
            return response()->json(['success' => false, 'error' => 'License not configured'], 400);
        }
        $baseUrl = rtrim($license->store_url, '/');
        $url = $baseUrl . '/client/addons';
        return $this->proxyMultipartPost($request, $url, $license->license_key);
    }

    private function proxyMultipartPost(Request $request, string $url, string $licenseKey): \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
    {
        try {
            $http = Http::timeout(300)
                ->withHeaders(['X-License-Key' => $licenseKey, 'Accept' => 'application/json']);

            foreach ($request->allFiles() as $key => $file) {
                if ($key !== 'file') {
                    continue;
                }

                $stream = fopen($file->getRealPath(), 'r');
                if ($stream === false) {
                    continue;
                }

                $http = $http->attach($key, $stream, $file->getClientOriginalName());
            }
            $params = $request->except(array_keys($request->allFiles())) ?: [];
            unset($params['_token']);
            $response = $http->post($url, $params);
            $status = $response->status();
            $contentType = $response->header('Content-Type') ?? 'application/json';
            if (str_contains($contentType, 'application/json')) {
                return response()->json($response->json() ?? ['success' => false, 'error' => 'Invalid response'], $status);
            }
            return response($response->body(), $status, ['Content-Type' => $contentType]);
        } catch (\Exception $e) {
            Log::warning('MCSetups proxy multipart POST failed', ['url' => $url, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    public function uploadProduct(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        set_time_limit(300);
        $license = MCSetupsLicense::firstOrFail();
        if (!$license->hasS3Config()) {
            $msg = 'S3 storage is not configured. Configure it in the license form above.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'error' => $msg], 400);
            }
            $this->alert->danger($msg)->flash();
            return redirect()->route('admin.mcsetups');
        }

        $validated = $request->validate([
            'product_file' => [
                'required',
                'file',
                'max:512000',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                        $fail('Archive must be .zip, .rar, .7z, .tar, or .gz.');
                    }
                },
            ],
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'placeholders' => 'nullable|string|max:10000',
            'required_addon_ids' => 'nullable|string|max:2000',
            'optional_addon_ids' => 'nullable|string|max:2000',
            'game_version' => 'nullable|string|max:64',
            'category' => 'nullable|string|max:255',
            'author_name' => 'nullable|string|max:255',
            'product_link' => 'nullable|string|max:1024',
            'cover_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'source_name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
        ]);

        $file = $request->file('product_file');
        $displayName = trim((string) $validated['display_name']);
        $ext = $file->getClientOriginalExtension() ?: 'zip';
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $displayName) . '_' . time() . '.' . $ext;
        $path = 'mcsetups-addons/products/' . $safeName;
        $base = pathinfo($path, PATHINFO_FILENAME);

        $coverUrl = null;
        if ($request->hasFile('cover_image')) {
            $coverUrl = $this->s3Service->uploadCoverImage($license, $request->file('cover_image'), 'products', $base);
        }

        $meta = [
            'display_name' => $displayName,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'game_version' => trim((string) ($validated['game_version'] ?? '')) ?: null,
            'category' => trim((string) ($validated['category'] ?? '')) ?: null,
            'author_name' => trim((string) ($validated['author_name'] ?? '')) ?: null,
            'placeholders' => [],
            'required_addon_ids' => [],
            'optional_addon_ids' => [],
            'product_link' => trim((string) ($validated['product_link'] ?? '')) ?: null,
            'cover_image_url' => $coverUrl,
            'source_name' => trim((string) ($validated['source_name'] ?? '')) ?: null,
            'price' => isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null ? (float) $validated['price'] : null,
        ];

        if (!empty($validated['placeholders'])) {
            $decoded = json_decode($validated['placeholders'], true);
            if (is_array($decoded)) {
                $meta['placeholders'] = $decoded;
            }
        }
        if (!empty($validated['required_addon_ids'])) {
            $decoded = json_decode($validated['required_addon_ids'], true);
            if (is_array($decoded)) {
                $meta['required_addon_ids'] = $decoded;
            }
        }
        if (!empty($validated['optional_addon_ids'])) {
            $decoded = json_decode($validated['optional_addon_ids'], true);
            if (is_array($decoded)) {
                $meta['optional_addon_ids'] = $decoded;
            }
        }

        try {
            $disk = $this->s3Service->getDisk($license);
            $disk->put($path, file_get_contents($file->getRealPath()), 'public');
            $disk->put('mcsetups-addons/products/' . $base . '.json', json_encode($meta, JSON_PRETTY_PRINT), 'public');

            Cache::forget('mcsetups:list_products');

            Log::info('MCSetups product uploaded to S3', ['path' => $path, 'display_name' => $displayName]);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => "Product uploaded: {$displayName}."]);
            }
            $this->alert->success("Product uploaded: {$displayName}.")->flash();
        } catch (\Exception $e) {
            Log::error('MCSetups product upload failed', ['error' => $e->getMessage()]);
            if ($request->ajax()) {
                return response()->json(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()], 500);
            }
            $this->alert->danger('Upload failed: ' . $e->getMessage())->flash();
        }

        return redirect()->route('admin.mcsetups');
    }

    public function deleteProduct(Request $request): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::firstOrFail();
        if (!$license->hasS3Config()) {
            return response()->json(['success' => false, 'error' => 'S3 storage is not configured.'], 400);
        }

        $validated = $request->validate([
            'path' => 'required|string|max:512',
        ]);

        $path = $validated['path'];
        if (!str_starts_with($path, 'mcsetups-addons/products/') || !preg_match('/\.(zip|rar|7z|tar|gz)$/i', $path)) {
            return response()->json(['success' => false, 'error' => 'Invalid product path.'], 400);
        }

        try {
            $disk = $this->s3Service->getDisk($license);
            $base = pathinfo($path, PATHINFO_FILENAME);
            $metaPath = dirname($path) . '/' . $base . '.json';

            $disk->delete($path);
            if ($disk->exists($metaPath)) {
                $disk->delete($metaPath);
            }

            Cache::forget('mcsetups:list_products');

            Log::info('MCSetups product deleted from S3', ['path' => $path]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('MCSetups product delete failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProduct(Request $request): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::firstOrFail();
        if (!$license->hasS3Config()) {
            return response()->json(['success' => false, 'error' => 'S3 storage is not configured.'], 400);
        }

        $validated = $request->validate([
            'path' => 'required|string|max:512',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'placeholders' => 'nullable|string|max:10000',
            'required_addon_ids' => 'nullable|string|max:2000',
            'optional_addon_ids' => 'nullable|string|max:2000',
            'game_version' => 'nullable|string|max:64',
            'category' => 'nullable|string|max:255',
            'author_name' => 'nullable|string|max:255',
            'product_link' => 'nullable|string|max:1024',
            'cover_image_url' => 'nullable|string|max:1024',
            'cover_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'source_name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
        ]);

        $path = $validated['path'];
        if (!str_starts_with($path, 'mcsetups-addons/products/') || !preg_match('/\.(zip|rar|7z|tar|gz)$/i', $path)) {
            return response()->json(['success' => false, 'error' => 'Invalid product path.'], 400);
        }

        $coverUrl = null;
        if ($request->hasFile('cover_image')) {
            $base = pathinfo($path, PATHINFO_FILENAME);
            $coverUrl = $this->s3Service->uploadCoverImage($license, $request->file('cover_image'), 'products', $base);
        } else {
            $coverUrl = trim((string) ($validated['cover_image_url'] ?? '')) ?: null;
        }

        $meta = [
            'display_name' => trim((string) $validated['display_name']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'game_version' => trim((string) ($validated['game_version'] ?? '')) ?: null,
            'category' => trim((string) ($validated['category'] ?? '')) ?: null,
            'author_name' => trim((string) ($validated['author_name'] ?? '')) ?: null,
            'placeholders' => [],
            'required_addon_ids' => [],
            'optional_addon_ids' => [],
            'product_link' => trim((string) ($validated['product_link'] ?? '')) ?: null,
            'cover_image_url' => $coverUrl,
            'source_name' => trim((string) ($validated['source_name'] ?? '')) ?: null,
            'price' => isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null ? (float) $validated['price'] : null,
        ];
        if (!empty($validated['placeholders'])) {
            $decoded = json_decode($validated['placeholders'], true);
            if (is_array($decoded)) {
                $meta['placeholders'] = $decoded;
            }
        }
        if (!empty($validated['required_addon_ids'])) {
            $decoded = json_decode($validated['required_addon_ids'], true);
            if (is_array($decoded)) {
                $meta['required_addon_ids'] = $decoded;
            }
        }
        if (!empty($validated['optional_addon_ids'])) {
            $decoded = json_decode($validated['optional_addon_ids'], true);
            if (is_array($decoded)) {
                $meta['optional_addon_ids'] = $decoded;
            }
        }

        try {
            $this->s3Service->updateProductMeta($license, $path, $meta);
            Cache::forget('mcsetups:list_products');
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('MCSetups product update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProductVersion(Request $request): \Illuminate\Http\JsonResponse
    {
        set_time_limit(300);
        $license = MCSetupsLicense::firstOrFail();
        if (!$license->hasS3Config()) {
            return response()->json(['success' => false, 'error' => 'S3 storage is not configured.'], 400);
        }

        $validated = $request->validate([
            'path' => 'required|string|max:512',
            'product_file' => [
                'required',
                'file',
                'max:512000',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                        $fail('Archive must be .zip, .rar, .7z, .tar, or .gz.');
                    }
                },
            ],
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'placeholders' => 'nullable|string|max:10000',
            'required_addon_ids' => 'nullable|string|max:2000',
            'optional_addon_ids' => 'nullable|string|max:2000',
            'game_version' => 'nullable|string|max:64',
            'category' => 'nullable|string|max:255',
            'author_name' => 'nullable|string|max:255',
            'product_link' => 'nullable|string|max:1024',
            'cover_image_url' => 'nullable|string|max:1024',
            'cover_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'source_name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
        ]);

        $path = $validated['path'];
        if (!str_starts_with($path, 'mcsetups-addons/products/') || !preg_match('/\.(zip|rar|7z|tar|gz)$/i', $path)) {
            return response()->json(['success' => false, 'error' => 'Invalid product path.'], 400);
        }

        $coverUrl = null;
        if ($request->hasFile('cover_image')) {
            $base = pathinfo($path, PATHINFO_FILENAME);
            $coverUrl = $this->s3Service->uploadCoverImage($license, $request->file('cover_image'), 'products', $base);
        } else {
            $coverUrl = trim((string) ($validated['cover_image_url'] ?? '')) ?: null;
        }

        $meta = [
            'display_name' => trim((string) $validated['display_name']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'game_version' => trim((string) ($validated['game_version'] ?? '')) ?: null,
            'category' => trim((string) ($validated['category'] ?? '')) ?: null,
            'author_name' => trim((string) ($validated['author_name'] ?? '')) ?: null,
            'placeholders' => [],
            'required_addon_ids' => [],
            'optional_addon_ids' => [],
            'product_link' => trim((string) ($validated['product_link'] ?? '')) ?: null,
            'cover_image_url' => $coverUrl,
            'source_name' => trim((string) ($validated['source_name'] ?? '')) ?: null,
            'price' => isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null ? (float) $validated['price'] : null,
        ];
        if (!empty($validated['placeholders'])) {
            $decoded = json_decode($validated['placeholders'], true);
            if (is_array($decoded)) {
                $meta['placeholders'] = $decoded;
            }
        }
        if (!empty($validated['required_addon_ids'])) {
            $decoded = json_decode($validated['required_addon_ids'], true);
            if (is_array($decoded)) {
                $meta['required_addon_ids'] = $decoded;
            }
        }
        if (!empty($validated['optional_addon_ids'])) {
            $decoded = json_decode($validated['optional_addon_ids'], true);
            if (is_array($decoded)) {
                $meta['optional_addon_ids'] = $decoded;
            }
        }

        try {
            $file = $request->file('product_file');
            $this->s3Service->updateProductVersion($license, $path, $file->getRealPath(), $meta);
            Cache::forget('mcsetups:list_products');
            return response()->json(['success' => true, 'message' => 'Product version updated.']);
        } catch (\Exception $e) {
            Log::error('MCSetups product version update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateAddon(Request $request): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::firstOrFail();
        if (!$license->hasS3Config()) {
            return response()->json(['success' => false, 'error' => 'S3 storage is not configured.'], 400);
        }

        $validated = $request->validate([
            'path' => 'required|string|max:512',
            'display_name' => 'required|string|max:255',
            'file_type' => 'nullable|string|max:128',
            'file_location' => 'nullable|string|max:255',
            'placeholders' => 'nullable|string|max:10000',
            'product_link' => 'nullable|string|max:1024',
            'cover_image_url' => 'nullable|string|max:1024',
            'cover_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'source_name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
        ]);

        $path = $validated['path'];
        if (!str_starts_with($path, 'mcsetups-addons/') || str_starts_with($path, 'mcsetups-addons/products/')) {
            return response()->json(['success' => false, 'error' => 'Invalid addon path.'], 400);
        }

        $placeholders = [];
        if (!empty($validated['placeholders'])) {
            $decoded = json_decode($validated['placeholders'], true);
            if (is_array($decoded)) {
                $placeholders = $decoded;
            }
        }

        $coverUrl = null;
        if ($request->hasFile('cover_image')) {
            $base = pathinfo($path, PATHINFO_FILENAME);
            $coverUrl = $this->s3Service->uploadCoverImage($license, $request->file('cover_image'), 'addons', $base);
        } else {
            $coverUrl = trim((string) ($validated['cover_image_url'] ?? '')) ?: null;
        }

        $meta = [
            'display_name' => trim((string) $validated['display_name']),
            'file_type' => trim((string) ($validated['file_type'] ?? '')) ?: null,
            'file_location' => trim((string) ($validated['file_location'] ?? '')) ?: null,
            'placeholders' => $placeholders,
            'product_link' => trim((string) ($validated['product_link'] ?? '')) ?: null,
            'cover_image_url' => $coverUrl,
            'source_name' => trim((string) ($validated['source_name'] ?? '')) ?: null,
            'price' => isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null ? (float) $validated['price'] : null,
        ];

        try {
            $this->s3Service->updateAddonMeta($license, $path, $meta);
            Cache::forget('mcsetups:list_addons');
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('MCSetups addon update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateAddonVersion(Request $request): \Illuminate\Http\JsonResponse
    {
        set_time_limit(300);
        $license = MCSetupsLicense::firstOrFail();
        if (!$license->hasS3Config()) {
            return response()->json(['success' => false, 'error' => 'S3 storage is not configured.'], 400);
        }

        $validated = $request->validate([
            'path' => 'required|string|max:512',
            'addon_file' => [
                'required',
                'file',
                'max:512000',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['zip', 'mcaddon', 'mcpack', 'mctemplate'])) {
                        $fail('File must be .zip, .mcaddon, .mcpack, or .mctemplate.');
                    }
                },
            ],
            'display_name' => 'required|string|max:255',
            'file_type' => 'nullable|string|max:128',
            'file_location' => 'nullable|string|max:255',
            'placeholders' => 'nullable|string|max:10000',
            'product_link' => 'nullable|string|max:1024',
            'cover_image_url' => 'nullable|string|max:1024',
            'cover_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'source_name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
        ]);

        $path = $validated['path'];
        if (!str_starts_with($path, 'mcsetups-addons/') || str_starts_with($path, 'mcsetups-addons/products/')) {
            return response()->json(['success' => false, 'error' => 'Invalid addon path.'], 400);
        }

        $placeholders = [];
        if (!empty($validated['placeholders'])) {
            $decoded = json_decode($validated['placeholders'], true);
            if (is_array($decoded)) {
                $placeholders = $decoded;
            }
        }

        $coverUrl = null;
        if ($request->hasFile('cover_image')) {
            $base = pathinfo($path, PATHINFO_FILENAME);
            $coverUrl = $this->s3Service->uploadCoverImage($license, $request->file('cover_image'), 'addons', $base);
        } else {
            $coverUrl = trim((string) ($validated['cover_image_url'] ?? '')) ?: null;
        }

        $meta = [
            'display_name' => trim((string) $validated['display_name']),
            'file_type' => trim((string) ($validated['file_type'] ?? '')) ?: null,
            'file_location' => trim((string) ($validated['file_location'] ?? '')) ?: null,
            'placeholders' => $placeholders,
            'product_link' => trim((string) ($validated['product_link'] ?? '')) ?: null,
            'cover_image_url' => $coverUrl,
            'source_name' => trim((string) ($validated['source_name'] ?? '')) ?: null,
            'price' => isset($validated['price']) && $validated['price'] !== '' && $validated['price'] !== null ? (float) $validated['price'] : null,
        ];

        try {
            $file = $request->file('addon_file');
            $this->s3Service->updateAddonVersion($license, $path, $file->getRealPath(), $meta);
            Cache::forget('mcsetups:list_addons');
            return response()->json(['success' => true, 'message' => 'Add-on version updated.']);
        } catch (\Exception $e) {
            Log::error('MCSetups addon version update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteAddon(Request $request): \Illuminate\Http\JsonResponse
    {
        $license = MCSetupsLicense::firstOrFail();
        if (!$license->hasS3Config()) {
            return response()->json(['success' => false, 'error' => 'S3 storage is not configured.'], 400);
        }

        $validated = $request->validate([
            'path' => 'required|string|max:512',
        ]);

        $path = $validated['path'];
        if (!str_starts_with($path, 'mcsetups-addons/') || str_starts_with($path, 'mcsetups-addons/products/')) {
            return response()->json(['success' => false, 'error' => 'Invalid addon path.'], 400);
        }

        try {
            $this->s3Service->deleteAddon($license, $path);
            Cache::forget('mcsetups:list_addons');
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('MCSetups addon delete failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function clearLicenseCaches(string $storeUrl, string $licenseKey): void
    {
        $validateCacheKey = 'mcsetups:validate_license:' . md5($storeUrl . ':' . $licenseKey);
        $storeFilesCacheKey = 'mcsetups:store_files:' . md5($storeUrl . ':' . $licenseKey);

        Cache::forget($validateCacheKey);
        Cache::forget($storeFilesCacheKey);

        Log::info('MCSetups: Cleared license caches', [
            'store_url' => $storeUrl,
            'validate_cache_key' => $validateCacheKey,
            'store_files_cache_key' => $storeFilesCacheKey,
        ]);
    }
}

