<?php

namespace Pterodactyl\Jobs\MCSetups;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\MCSetupsLicense;
use Pterodactyl\Services\Servers\MCSetups\UrlResolverService;

class ValidateLicenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 1;

    private function isPlaceholderLicense(string $licenseKey): bool
    {
        if (empty($licenseKey)) {
            return true;
        }

        $placeholderTexts = [
            'Unable to acquire a license key automatically, please contact the creator directly.',
            'Unable to acquire a license key automatically, please contact the creator directly.',
        ];

        foreach ($placeholderTexts as $placeholder) {
            if (str_contains($licenseKey, $placeholder)) {
                return true;
            }
        }

        return false;
    }

    public function handle(): void
    {
        Log::info('MCSetups: Starting license pre-validation job');

        $licenses = MCSetupsLicense::all();

        if ($licenses->isEmpty()) {
            Log::info('MCSetups: No licenses found in database');
            return;
        }

        $validatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($licenses as $license) {
            if (empty($license->store_url) || empty($license->license_key)) {
                Log::debug('MCSetups: Skipping license with empty store_url or license_key', [
                    'license_id' => $license->id,
                ]);
                continue;
            }

            if ($this->isPlaceholderLicense($license->license_key)) {
                Log::debug('MCSetups: Skipping placeholder license', [
                    'license_id' => $license->id,
                ]);
                $skippedCount++;
                continue;
            }

            $licenseHash = md5($license->store_url . ':' . $license->license_key);
            $cacheKey = 'mcsetups:prevalidated_license:' . $licenseHash;

            try {
                // Resolve shortened URLs (e.g., ptero.co) if needed
                $urlResolver = app(UrlResolverService::class);
                $resolvedStoreUrl = $urlResolver->resolveUrl($license->store_url);
                
                // Extract base URL from resolved URL (in case it was a Pterodactyl Panel URL)
                $extractedBaseUrl = $urlResolver->extractStoreUrlFromPterodactylUrl($resolvedStoreUrl);
                $finalStoreUrl = $extractedBaseUrl ?: rtrim($resolvedStoreUrl, '/');
                
                $validateUrl = $finalStoreUrl . '/store/validate';

                Log::debug('MCSetups: Validating license in background job', [
                    'license_id' => $license->id,
                    'store_url' => $license->store_url,
                    'resolved_store_url' => $finalStoreUrl,
                    'validate_url' => $validateUrl,
                ]);

                $response = Http::timeout(60)
                    ->withHeaders([
                        'X-License-Key' => $license->license_key,
                    ])
                    ->post($validateUrl, [
                        'license_key' => $license->license_key,
                    ]);

                $responseJson = $response->json();

                if ($response->successful()) {
                    Cache::put($cacheKey, [
                        'valid' => true,
                        'cached_at' => now()->toIso8601String(),
                    ], 120);
                    $license->update(['is_active' => true]);
                    Log::debug('MCSetups: License pre-validated successfully', [
                        'license_id' => $license->id,
                    ]);
                    $validatedCount++;
                } else {
                    $errorMessage = $responseJson['message'] ?? $responseJson['reason'] ?? $responseJson['error'] ?? '';
                    $errorMessageLower = strtolower($errorMessage);

                    $isLicenseError = false;
                    if (!empty($errorMessage)) {
                        $licenseErrorKeywords = ['expired', 'invalid license', 'license invalid', 'license expired', 'license key', 'unauthorized', 'forbidden'];
                        foreach ($licenseErrorKeywords as $keyword) {
                            if (str_contains($errorMessageLower, $keyword)) {
                                $isLicenseError = true;
                                break;
                            }
                        }
                    }

                    if ($isLicenseError || ($response->status() === 403 && !empty($errorMessage))) {
                        Cache::put($cacheKey, [
                            'valid' => false,
                            'reason' => 'license_rejected',
                            'error' => $errorMessage,
                            'cached_at' => now()->toIso8601String(),
                        ], 120);
                        $license->update(['is_active' => false]);
                        Log::warning('MCSetups: License pre-validation failed - server rejected license', [
                            'license_id' => $license->id,
                            'error' => $errorMessage,
                        ]);
                    } else {
                        Cache::put($cacheKey, [
                            'valid' => false,
                            'reason' => 'validation_failed_network',
                            'http_status' => $response->status(),
                            'cached_at' => now()->toIso8601String(),
                        ], 120);

                        Log::warning('MCSetups: License pre-validation failed - network issue', [
                            'license_id' => $license->id,
                            'http_status' => $response->status(),
                        ]);
                    }
                    $failedCount++;
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Cache::put($cacheKey, [
                    'valid' => false,
                    'reason' => 'connection_error',
                    'error' => $e->getMessage(),
                    'cached_at' => now()->toIso8601String(),
                ], 120);

                Log::warning('MCSetups: License pre-validation connection error', [
                    'license_id' => $license->id,
                    'error' => $e->getMessage(),
                ]);
                $failedCount++;
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $isTimeout = str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'timed out');

                Cache::put($cacheKey, [
                    'valid' => false,
                    'reason' => $isTimeout ? 'timeout' : 'request_error',
                    'error' => $e->getMessage(),
                    'cached_at' => now()->toIso8601String(),
                ], 120);

                Log::warning('MCSetups: License pre-validation request error', [
                    'license_id' => $license->id,
                    'error' => $e->getMessage(),
                    'is_timeout' => $isTimeout,
                ]);
                $failedCount++;
            } catch (\Exception $e) {
                Cache::put($cacheKey, [
                    'valid' => false,
                    'reason' => 'unknown_error',
                    'error' => $e->getMessage(),
                    'cached_at' => now()->toIso8601String(),
                ], 120);

                Log::error('MCSetups: License pre-validation exception', [
                    'license_id' => $license->id,
                    'error' => $e->getMessage(),
                ]);
                $failedCount++;
            }
        }

        Log::info('MCSetups: License pre-validation job completed', [
            'total_licenses' => $licenses->count(),
            'validated' => $validatedCount,
            'skipped' => $skippedCount,
            'failed' => $failedCount,
        ]);
    }
}

