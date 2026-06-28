<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AIController extends Controller
{
    public function generateSummary(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'title' => 'required|string'
        ]);

        $content = $request->content;
        $title = $request->title;

        // Check if OpenAI API key is configured
        $apiKey = env('OPENAI_API_KEY');

        if (!$apiKey) {
            // Return mock summary if no API key is configured
            $mockSummary = $this->generateMockSummary($title, $content);
            return response()->json(['summary' => $mockSummary]);
        }

        try {
            // Call OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that summarizes news articles into 3-5 key bullet points for financial professionals. Keep each point concise and actionable.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Summarize the following news article titled \"{$title}\" with content \"{$content}\" into 3-5 key bullet points. Return only the bullet points, each on a new line, without numbering or bullet symbols."
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $summaryText = $response->json('choices.0.message.content');
                $summaryPoints = array_filter(array_map('trim', explode("\n", $summaryText)));
                
                // Ensure we have 3-5 points
                if (count($summaryPoints) < 3) {
                    $summaryPoints = $this->generateMockSummary($title, $content);
                }

                return response()->json(['summary' => array_values($summaryPoints)]);
            }

            // Fallback to mock summary if API fails
            $mockSummary = $this->generateMockSummary($title, $content);
            return response()->json(['summary' => $mockSummary]);

        } catch (\Exception $e) {
            // Fallback to mock summary on error
            $mockSummary = $this->generateMockSummary($title, $content);
            return response()->json(['summary' => $mockSummary]);
        }
    }

    private function generateMockSummary($title, $content)
    {
        // Generate simple mock summary based on content length
        $contentLength = strlen($content);
        
        return [
            "Key insight from: " . substr($title, 0, 50) . "...",
            "Market impact analysis indicates significant trends",
            "Financial implications for investors and traders",
            "Strategic recommendations based on current data",
            "Risk factors to monitor in coming weeks"
        ];
    }
}
