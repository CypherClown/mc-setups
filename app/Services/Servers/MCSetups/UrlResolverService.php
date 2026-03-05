<?php

namespace Pterodactyl\Services\Servers\MCSetups;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UrlResolverService
{
    /**
     * Resolve a shortened URL (e.g., ptero.co) to its final destination
     * 
     * @param string $url The URL to resolve
     * @return string The resolved URL, or original URL if resolution fails
     */
    public function resolveUrl(string $url): string
    {
        if (empty($url)) {
            return $url;
        }

        // Check if it's a ptero.co shortened URL
        if (!preg_match('/^https?:\/\/(www\.)?ptero\.co\//i', $url)) {
            return $url;
        }

        // Check cache first
        $cacheKey = 'mcsetups:resolved_url:' . md5($url);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug('MCSetups: Using cached resolved URL', [
                'original' => $url,
                'resolved' => $cached,
            ]);
            return $cached;
        }

        try {
            Log::info('MCSetups: Resolving shortened URL', [
                'url' => $url,
            ]);

            // Follow redirects to get the final URL
            $response = Http::timeout(10)
                ->withoutRedirecting()
                ->head($url);

            // If we get a redirect, try to get the Location header
            if ($response->status() >= 300 && $response->status() < 400) {
                $location = $response->header('Location');
                if (!empty($location)) {
                    // Make absolute URL if it's relative
                    if (strpos($location, 'http') !== 0) {
                        $parsed = parse_url($url);
                        $location = $parsed['scheme'] . '://' . $parsed['host'] . 
                                   (isset($parsed['port']) ? ':' . $parsed['port'] : '') . 
                                   (strpos($location, '/') === 0 ? $location : '/' . $location);
                    }
                    
                    // Cache for 24 hours
                    Cache::put($cacheKey, $location, 86400);
                    
                    Log::info('MCSetups: URL resolved successfully', [
                        'original' => $url,
                        'resolved' => $location,
                    ]);
                    
                    return $location;
                }
            }

            // If HEAD doesn't work, try GET with redirect following disabled
            $response = Http::timeout(10)
                ->withoutRedirecting()
                ->get($url);

            if ($response->status() >= 300 && $response->status() < 400) {
                $location = $response->header('Location');
                if (!empty($location)) {
                    // Make absolute URL if it's relative
                    if (strpos($location, 'http') !== 0) {
                        $parsed = parse_url($url);
                        $location = $parsed['scheme'] . '://' . $parsed['host'] . 
                                   (isset($parsed['port']) ? ':' . $parsed['port'] : '') . 
                                   (strpos($location, '/') === 0 ? $location : '/' . $location);
                    }
                    
                    // Cache for 24 hours
                    Cache::put($cacheKey, $location, 86400);
                    
                    Log::info('MCSetups: URL resolved successfully', [
                        'original' => $url,
                        'resolved' => $location,
                    ]);
                    
                    return $location;
                }
            }

            // If no redirect found, return original URL
            Log::warning('MCSetups: No redirect found for URL', [
                'url' => $url,
                'status' => $response->status(),
            ]);
            
            return $url;
        } catch (\Exception $e) {
            Log::warning('MCSetups: Failed to resolve URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            
            // Return original URL on error
            return $url;
        }
    }

    /**
     * Extract store URL from a Pterodactyl Panel URL
     * 
     * @param string $url The Pterodactyl Panel URL
     * @return string|null The store URL, or null if not found
     */
    public function extractStoreUrlFromPterodactylUrl(string $url): ?string
    {
        $resolved = $this->resolveUrl($url);
        
        // Try to extract the base URL from the resolved URL
        // Pterodactyl Panel URLs typically follow patterns like:
        // https://panel.example.com/servers/{uuid}/...
        // We want to extract the base domain
        
        $parsed = parse_url($resolved);
        if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
            $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
            if (isset($parsed['port'])) {
                $baseUrl .= ':' . $parsed['port'];
            }
            return $baseUrl;
        }
        
        return null;
    }
}

