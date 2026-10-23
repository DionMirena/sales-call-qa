<?php

namespace Tests\Feature;

use App\Services\DeepgramTranscriptionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepgramTranscriptionTest extends TestCase
{
    public function test_sends_token_auth_and_options_as_query_parameters(): void
    {
        config(['qa.deepgram_key' => 'test-key', 'qa.language' => 'en']);

        Http::fake([
            'api.deepgram.com/*' => Http::response(['results' => ['utterances' => [
                ['speaker' => 0, 'start' => 0.28, 'end' => 4.1, 'transcript' => 'Hello, this is Sarah.'],
                ['speaker' => 1, 'start' => 11.1, 'end' => 12.0, 'transcript' => "Sure. That's fine."],
            ]]]),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'dg');
        file_put_contents($path, 'fake mp3 bytes');

        $result = (new DeepgramTranscriptionService)->transcribe($path);

        Http::assertSent(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->hasHeader('Authorization', 'Token test-key')
                && $query['diarize'] === 'true'
                && $query['utterances'] === 'true'
                && $query['language'] === 'en'
                && $request->body() === 'fake mp3 bytes';
        });

        $this->assertSame(12, $result['duration_seconds']);
        $this->assertSame(280, $result['segments'][0]['start_ms']);
        $this->assertSame(1, $result['segments'][1]['speaker']);

        unlink($path);
    }
}
