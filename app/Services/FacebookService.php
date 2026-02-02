<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FacebookService
{
    protected string $pageId;
    protected string $accessToken;
    protected string $graphVersion = 'v24.0';

    public function __construct()
    {
        $this->pageId = config('services.facebook.page_id') ?? env('FB_PAGE_ID');
        $this->accessToken = config('services.facebook.token') ?? env('FB_PAGE_ACCESS_TOKEN');
    }

    /**
     * Publish a post to the Facebook Page.
     *
     * @param string $content
     * @return string The Facebook Post ID
     * @throws Exception
     */
    public function publishPost(string $content): string
    {
        if (empty($this->pageId) || empty($this->accessToken)) {
            throw new Exception('Facebook Page ID and Access Token are required but not configured.');
        }

        $url = "https://graph.facebook.com/{$this->graphVersion}/{$this->pageId}/feed";

        try {
            $response = Http::withoutVerifying()->post($url, [
                'message' => $content,
                'access_token' => $this->accessToken,
            ]);

            if ($response->failed()) {
                $errorData = $response->json();
                $errorMessage = $errorData['error']['message'] ?? 'Unknown Facebook API Error';
                Log::channel('facebook')->error('Facebook API Error: ' . $errorMessage, ['response' => $errorData]);
                throw new Exception("Facebook API failed: " . $errorMessage);
            }

            $data = $response->json();

            if (!isset($data['id'])) {
                Log::channel('facebook')->error('Facebook API response missing ID', ['response' => $data]);
                throw new Exception('Invalid response from Facebook API: ID not found.');
            }

            return $data['id'];

        } catch (Exception $e) {
            Log::channel('facebook')->error('FacebookService Exception: ' . $e->getMessage());
            throw $e;
        }
    }
}
