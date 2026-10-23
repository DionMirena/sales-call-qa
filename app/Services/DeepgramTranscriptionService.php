<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepgramTranscriptionService implements TranscriptionService
{
    public function transcribe(string $path): array
    {
        $apiKey   = config('qa.deepgram_key');
        $language = config('qa.language', 'en');
        $company  = (string) config('qa.company_name');

        // Deepgram API keys use the "Token" scheme, not "Bearer".
        // Options must be query parameters because the request body is the audio itself.
        $response = Http::withToken($apiKey, 'Token')
            ->timeout(900)
            ->withQueryParameters(array_filter([
                'model'        => 'nova-2',
                'diarize'      => 'true',
                'utterances'   => 'true',
                'punctuate'    => 'true',
                'smart_format' => 'true',
                'language'     => $language,
                // Made-up brand names are often misheard, so boost the company name.
                'keywords'     => $company !== '' ? ucfirst(mb_strtolower($company)) . ':3' : null,
            ]))
            ->withBody(file_get_contents($path), 'audio/mpeg')
            ->post('https://api.deepgram.com/v1/listen');

        if (! $response->successful()) {
            throw new RuntimeException(
                'Deepgram API error ' . $response->status() . ': ' . $response->body()
            );
        }

        $data       = $response->json();
        $utterances = $data['results']['utterances'] ?? [];

        if (empty($utterances)) {
            throw new RuntimeException('Deepgram returned no utterances. The audio may be silent or unrecognised.');
        }

        $segments        = [];
        $totalDurationMs = 0;

        foreach ($utterances as $utterance) {
            $startMs = (int) round($utterance['start'] * 1000);
            $endMs   = (int) round($utterance['end'] * 1000);

            $segments[] = [
                'speaker'  => (int) ($utterance['speaker'] ?? 0),
                'start_ms' => $startMs,
                'end_ms'   => $endMs,
                // Show the company name in its official spelling (e.g. "Insurvas" → "INSURVAS").
                'text'     => $company !== ''
                    ? preg_replace('/\b' . preg_quote($company, '/') . '\b/iu', $company, $utterance['transcript'] ?? '')
                    : ($utterance['transcript'] ?? ''),
            ];

            $totalDurationMs = max($totalDurationMs, $endMs);
        }

        return [
            'segments'         => $segments,
            // Whole seconds of the audio itself, rounded down like the browser player shows it.
            'duration_seconds' => (int) floor($data['metadata']['duration'] ?? $totalDurationMs / 1000),
        ];
    }
}
