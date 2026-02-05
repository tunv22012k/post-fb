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
        $response = Http::withoutVerifying()->get('https://graph.facebook.com/v19.0/oauth/access_token', [
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
        $response = Http::withoutVerifying()->get('https://graph.facebook.com/v19.0/me/accounts', [
            'access_token' => $userAccessToken,
            'limit' => 1000, // Fetch up to 1000 pages
        ]);

        if ($response->failed()) {
            throw new Exception('Fetch Pages Failed: ' . $response->body());
        }

        // Return array of pages (id, name, access_token...)
        return $response->json()['data'] ?? [];
    }
    /**
     * Publish content to a specific Page
     */
    public function publishPostToPage(string $pageAccessToken, string $message): string
    {
        $response = Http::withoutVerifying()->post("https://graph.facebook.com/v19.0/me/feed", [
            'access_token' => $pageAccessToken,
            'message' => $message,
        ]);

        if ($response->failed()) {
            throw new Exception('Publish to Page Failed: ' . $response->body());
        }

        return $response->json()['id']; // Returns "PageID_PostID"
    }
    /**
     * Publish photo to a specific Page
     */
    public function publishPhotoToPage(string $pageAccessToken, string $message, string $imagePath): string
    {
        // Use attach for file upload
        $response = Http::withoutVerifying()
            ->attach('source', file_get_contents($imagePath), basename($imagePath))
            ->post("https://graph.facebook.com/v19.0/me/photos", [
                'access_token' => $pageAccessToken,
                'message' => $message,
            ]);

        if ($response->failed()) {
            throw new Exception('Publish Photo to Page Failed: ' . $response->body());
        }

        // Returns "PostID" (or Photo ID, which acts as Post ID for feed)
        $data = $response->json();
        return $data['post_id'] ?? $data['id']; 
    }
}
