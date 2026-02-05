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
     * @param array $config (temperature, maxOutputTokens, etc.)
     * @return string
     * @throws Exception
     */
    public function generateContent(string $prompt, array $config = []): string
    {
        if (empty($this->apiKey)) {
            throw new Exception('Gemini API Key is not configured.');
        }

        $url = "{$this->baseUrl}?key={$this->apiKey}";
        
        $temperature = $config['temperature'] ?? 0.7;

        try {
            $response = Http::withoutVerifying() // Fix for local SSL issues
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => $temperature,
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

    /**
     * Rewrite content based on a specific persona and language.
     */
    public function rewriteContent(string $content, string $persona, string $language = 'vi'): string
    {
        $prompt = $this->getPersonaPrompt($persona, $content, $language);
        
        // Adjust temperature based on persona to ensure uniqueness
        $temperature = match ($persona) {
            'KOL' => 0.9, // Creative, bold
            'Expert' => 0.5, // Precise, professional
            'GenZ' => 0.8, // Slang, emoji, dynamic
            'Fun' => 0.85, 
            'Formal' => 0.4,
            default => 0.7,
        };

        return $this->generateContent($prompt, ['temperature' => $temperature]);
    }

    protected function getPersonaPrompt(string $persona, string $content, string $language): string
    {
        $langInstruction = match($language) {
            'en' => "Write in English.",
            'vi' => "Write in Vietnamese.",
            default => "Write in Vietnamese.", // Default
        };

        $baseInstruction = "Rewrite the following content for a Facebook post. $langInstruction Keep the core meaning but change the tone and structure completely.";
        
        return match ($persona) {
            'KOL' => "$baseInstruction Tone: Inspirational, energetic, leadership vibe. Use emojis moderately. Content: $content",
            'Expert' => "$baseInstruction Tone: Professional, analytical, trustworthy. Use data-driven language if possible. No emojis. Content: $content",
            'GenZ' => "$baseInstruction Tone: Trendy, using Gen Z slang (but understandable), heavy use of emojis, short sentences. Content: $content",
            'Fun' => "$baseInstruction Tone: Humorous, witty, light-hearted. Use fun emojis. Content: $content",
            'Formal' => "$baseInstruction Tone: Official, serious, respectful. Minimal or no emojis. Content: $content",
            default => "$baseInstruction Tone: Friendly and engaging. Content: $content",
        };
    }
}
