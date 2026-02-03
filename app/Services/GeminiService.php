<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY') ?? '';
    }

    /**
     * Generate content using Gemini API based on a prompt.
     *
     * @param string $prompt
     * @return string
     * @throws Exception
     */
    public function generateContent(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new Exception('Gemini API Key is not configured.');
        }

        $url = "{$this->baseUrl}?key={$this->apiKey}";

        try {
            $response = Http::withoutVerifying() // Fix for local SSL issues
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]);

            if ($response->failed()) {
                $errorData = $response->json();
                $errorMessage = $errorData['error']['message'] ?? 'Unknown Gemini API Error';
                Log::channel('facebook')->error('Gemini API Error: ' . $errorMessage, ['response' => $errorData]);
                throw new Exception("Gemini API failed: " . $errorMessage);
            }

            $data = $response->json();
            
            // Extract text from Gemini response structure
            $generatedText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($generatedText)) {
                Log::channel('facebook')->error('Gemini API response format unexpected', ['response' => $data]);
                throw new Exception('Invalid response from Gemini API: Text not found.');
            }

            return trim($generatedText);

        } catch (Exception $e) {
            Log::channel('facebook')->error('GeminiService Exception: ' . $e->getMessage());
            throw $e;
        }
    }
}
