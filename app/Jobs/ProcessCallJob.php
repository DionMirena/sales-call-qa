<?php

namespace App\Jobs;

use App\Models\Call;
use App\Services\ScoringService;
use App\Services\TranscriptionService;
use App\Support\AudioNormaliser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessCallJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 900;
    public int $tries   = 2;

    public function __construct(
        public readonly int  $callId,
        public readonly bool $rescoreOnly = false,
    ) {}

    public function handle(
        AudioNormaliser     $normaliser,
        TranscriptionService $transcription,
        ScoringService       $scoring,
    ): void {
        $call = Call::findOrFail($this->callId);

        try {
            if (! $this->rescoreOnly) {
                // 1 — Normalise
                $call->update(['status' => 'normalising']);

                $inputPath  = Storage::disk('private')->path($call->original_path);
                $normalised = 'calls/' . $call->id . '/audio.mp3';
                $outputPath = Storage::disk('private')->path($normalised);

                @mkdir(dirname($outputPath), 0755, true);
                $normaliser->normalise($inputPath, $outputPath);
                $call->update(['audio_path' => $normalised]);

                // 2 — Transcribe
                $call->update(['status' => 'transcribing']);
                $result = $transcription->transcribe($outputPath);

                // 3 — Store segments
                $segments = $result['segments'];
                $bulk = [];
                foreach ($segments as $i => $seg) {
                    $bulk[] = [
                        'call_id'  => $call->id,
                        'code'     => 'S' . ($i + 1),
                        'speaker'  => $seg['speaker'],
                        'start_ms' => $seg['start_ms'],
                        'end_ms'   => $seg['end_ms'],
                        'text'     => $seg['text'],
                    ];
                }
                \App\Models\TranscriptSegment::insert($bulk);

                $call->update([
                    'duration_seconds' => $result['duration_seconds'],
                    'agent_speaker'    => static::guessAgentSpeaker($segments),
                ]);
            }

            // 4 — Score
            $call->update(['status' => 'scoring']);
            $scoring->score($call->fresh());

        } catch (Throwable $e) {
            $call->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        // Final failure after all retries exhausted
        Call::where('id', $this->callId)
            ->whereNotIn('status', ['done'])
            ->update([
                'status'        => 'failed',
                'error_message' => 'Processing failed after ' . $this->tries . ' attempts: ' . $e->getMessage(),
            ]);
    }

    /**
     * Prefer the speaker who introduces themselves ("my name is", "this is <Name>",
     * or mentions the company name) within the first 10 segments.
     * Falls back to the first speaker if no match is found.
     */
    public static function guessAgentSpeaker(array $segments): int
    {
        $companyName = strtolower((string) config('qa.company_name', ''));
        $head        = array_slice($segments, 0, 10);

        // Strongest signal first: a customer may answer "Hello, this is Tom",
        // but only the agent names the company.
        $signals = [
            fn ($text) => $companyName !== '' && str_contains(strtolower($text), $companyName),
            fn ($text) => str_contains(strtolower($text), 'my name is'),
            fn ($text) => (bool) preg_match('/\b[Tt]his is [A-Z][a-z]+/', $text),
        ];

        foreach ($signals as $matches) {
            foreach ($head as $seg) {
                if ($matches($seg['text'] ?? '')) {
                    return (int) $seg['speaker'];
                }
            }
        }

        return (int) ($segments[0]['speaker'] ?? 0);
    }
}
