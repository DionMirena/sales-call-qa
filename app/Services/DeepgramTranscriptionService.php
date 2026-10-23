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

        $response = Http::withToken($apiKey)
            ->withHeaders(['Content-Type' => 'audio/mpeg'])
            ->withBody(file_get_contents($path), 'application/octet-stream')
            ->post('https://api.deepgram.com/v1/listen', [
                'diarize'      => 'true',
                'utterances'   => 'true',
                'punctuate'    => 'true',
                'smart_format' => 'true',
                'language'     => $language,
            ]);

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
                'text'     => $utterance['transcript'] ?? '',
            ];

            $totalDurationMs = max($totalDurationMs, $endMs);
        }

        return [
            'segments'         => $segments,
            'duration_seconds' => (int) ceil($totalDurationMs / 1000),
        ];
    }
}
