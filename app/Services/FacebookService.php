<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class FacebookService
{
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.facebook.client_id');
        $this->clientSecret = config('services.facebook.client_secret');
    }

    /**
     * Exchange Short-Lived Token for Long-Lived Token (60 days)
     */
    public function exchangeToken(string $shortToken): string
    {
        // Use v19.0 as a stable version
        $response = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'fb_exchange_token' => $shortToken,
        ]);

        if ($response->failed()) {
            throw new Exception('Facebook Token Exchange Failed: ' . $response->body());
        }

        return $response->json()['access_token'];
    }

    /**
     * Fetch all pages with their tokens using the Long-Lived User Token
     */
    public function getPages(string $userAccessToken): array
    {
        $response = Http::get('https://graph.facebook.com/v19.0/me/accounts', [
            'access_token' => $userAccessToken,
            'limit' => 1000, // Fetch up to 1000 pages
        ]);

        if ($response->failed()) {
            throw new Exception('Fetch Pages Failed: ' . $response->body());
        }

        // Return array of pages (id, name, access_token...)
        return $response->json()['data'] ?? [];
    }
}
