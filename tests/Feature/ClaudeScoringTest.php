<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Services\ClaudeScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ClaudeScoringTest extends TestCase
{
    use RefreshDatabase;

    // ── Test data helpers ─────────────────────────────────────────────────────

    private function setupCall(): array
    {
        $user  = User::factory()->create(['role' => 'manager']);
        $agent = Agent::factory()->create();

        $template  = Template::factory()->create(['created_by' => $user->id]);
        $criterion = $template->criteria()->create([
            'position'    => 0,
            'name'        => 'Agent Introduction',
            'description' => 'Agent introduces themselves and company.',
            'type'        => 'pass_fail',
            'weight'      => 1,
            'is_critical' => false,
            'phase_hint'  => 'opening',
        ]);

        $call = Call::create([
            'agent_id'          => $agent->id,
            'template_id'       => $template->id,
            'uploaded_by'       => $user->id,
            'original_path'     => 'calls/test.mp3',
            'original_filename' => 'test.mp3',
            'status'            => 'scoring',
        ]);

        TranscriptSegment::insert([[
            'call_id'  => $call->id,
            'code'     => 'S1',
            'speaker'  => 0,
            'start_ms' => 1000,
            'end_ms'   => 8000,
            'text'     => 'Hello, this is Sarah Mitchell from Clearview Life Insurance.',
        ]]);

        return [$call, $criterion];
    }

    private function validResponse(int $criterionId): array
    {
        return [
            'id'      => 'msg_test',
            'type'    => 'message',
            'role'    => 'assistant',
            'content' => [[
                'type'  => 'tool_use',
                'id'    => 'tu_test',
                'name'  => 'score_call',
                'input' => [
                    'results' => [[
                        'criterion_id' => $criterionId,
                        'result'       => 'pass',
                        'score'        => null,
                        'confidence'   => 0.95,
                        'reasoning'    => 'Agent clearly introduced themselves.',
                        'evidence'     => [[
                            'segment_code' => 'S1',
                            'quote'        => 'Hello, this is Sarah Mitchell from Clearview Life Insurance.',
                        ]],
                    ]],
                ],
            ]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 60],
        ];
    }

    private function invalidResponse(int $criterionId): array
    {
        return [
            'id'      => 'msg_bad',
            'type'    => 'message',
            'role'    => 'assistant',
            'content' => [[
                'type'  => 'tool_use',
                'id'    => 'tu_bad',
                'name'  => 'score_call',
                'input' => [
                    'results' => [[
                        'criterion_id' => $criterionId,
                        'result'       => 'maybe',  // invalid for pass_fail
                        'confidence'   => 0.7,
                        'reasoning'    => 'Uncertain.',
                        'evidence'     => [],
                    ]],
                ],
            ]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 30],
        ];
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_valid_response_stores_criterion_result_and_marks_done(): void
    {
        [$call, $criterion] = $this->setupCall();

        Http::fake([
            'https://api.anthropic.com/*' => Http::response($this->validResponse($criterion->id)),
        ]);

        app(ClaudeScoringService::class)->score($call->fresh());

        $call->refresh();
        $this->assertEquals('done', $call->status);
        $this->assertNotNull($call->score_percent);
        $this->assertDatabaseHas('criterion_results', [
            'call_id'           => $call->id,
            'criterion_id'      => $criterion->id,
            'ai_result'         => 'pass',
            'verification_status' => 'verified',
        ]);
    }

    public function test_evidence_verification_status_is_stored(): void
    {
        [$call, $criterion] = $this->setupCall();

        Http::fake([
            'https://api.anthropic.com/*' => Http::response($this->validResponse($criterion->id)),
        ]);

        app(ClaudeScoringService::class)->score($call->fresh());

        $result = $call->criterionResults()->first();
        $this->assertEquals('verified', $result->verification_status);
        $this->assertFalse($result->needs_review);
    }

    public function test_token_usage_is_logged_without_transcript(): void
    {
        [$call, $criterion] = $this->setupCall();

        Http::fake([
            'https://api.anthropic.com/*' => Http::response($this->validResponse($criterion->id)),
        ]);

        $loggedContext = null;
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use (&$loggedContext) {
                if ($message === 'claude_scoring_tokens') {
                    $loggedContext = $context;
                    return true;
                }
                return false;
            });

        app(ClaudeScoringService::class)->score($call->fresh());

        $this->assertArrayHasKey('call_id', $loggedContext);
        $this->assertArrayHasKey('input_tokens', $loggedContext);
        $this->assertArrayNotHasKey('transcript', $loggedContext);
    }

    // ── Retry on bad output ───────────────────────────────────────────────────

    public function test_invalid_then_valid_response_succeeds_after_retry(): void
    {
        [$call, $criterion] = $this->setupCall();

        Http::fake([
            'https://api.anthropic.com/*' => Http::sequence()
                ->push($this->invalidResponse($criterion->id), 200)
                ->push($this->validResponse($criterion->id), 200),
        ]);

        app(ClaudeScoringService::class)->score($call->fresh());

        $this->assertEquals('done', $call->fresh()->status);
    }

    public function test_two_invalid_responses_throw_exception(): void
    {
        [$call, $criterion] = $this->setupCall();

        Http::fake([
            'https://api.anthropic.com/*' => Http::sequence()
                ->push($this->invalidResponse($criterion->id), 200)
                ->push($this->invalidResponse($criterion->id), 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid scoring output after retry/');

        app(ClaudeScoringService::class)->score($call->fresh());
    }

    // ── API error ─────────────────────────────────────────────────────────────

    public function test_anthropic_api_error_throws_runtime_exception(): void
    {
        [$call] = $this->setupCall();

        Http::fake([
            'https://api.anthropic.com/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Anthropic API error 401/');

        app(ClaudeScoringService::class)->score($call->fresh());
    }

    // ── Validation edge cases ─────────────────────────────────────────────────

    public function test_missing_criterion_in_response_is_a_validation_error(): void
    {
        [$call] = $this->setupCall();

        // Response with empty results — criterion is missing
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type'  => 'tool_use',
                    'name'  => 'score_call',
                    'input' => ['results' => []],
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        app(ClaudeScoringService::class)->score($call->fresh());
    }

    public function test_unknown_segment_code_in_evidence_is_a_validation_error(): void
    {
        [$call, $criterion] = $this->setupCall();

        $badResponse = $this->validResponse($criterion->id);
        // Override evidence with a bad segment code
        $badResponse['content'][0]['input']['results'][0]['evidence'][0]['segment_code'] = 'S999';

        Http::fake([
            'https://api.anthropic.com/*' => Http::sequence()
                ->push($badResponse, 200)
                ->push($badResponse, 200),  // Also fails on retry
        ]);

        $this->expectException(RuntimeException::class);

        app(ClaudeScoringService::class)->score($call->fresh());
    }

    // ── Scale type normalization ──────────────────────────────────────────────

    public function test_scale_1_5_score_normalized_to_0_1(): void
    {
        $user  = User::factory()->create(['role' => 'manager']);
        $agent = Agent::factory()->create();

        $template  = Template::factory()->create(['created_by' => $user->id]);
        $criterion = $template->criteria()->create([
            'position' => 0, 'name' => 'Rapport', 'description' => 'Rate rapport',
            'type' => 'scale_1_5', 'weight' => 1, 'is_critical' => false, 'phase_hint' => 'anywhere',
        ]);

        $call = Call::create([
            'agent_id' => $agent->id, 'template_id' => $template->id,
            'uploaded_by' => $user->id, 'original_path' => 'calls/t.mp3',
            'original_filename' => 't.mp3', 'status' => 'scoring',
        ]);

        TranscriptSegment::insert([[
            'call_id' => $call->id, 'code' => 'S1', 'speaker' => 0,
            'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Good morning',
        ]]);

        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type'  => 'tool_use',
                    'name'  => 'score_call',
                    'input' => ['results' => [[
                        'criterion_id' => $criterion->id,
                        'result'       => '4',
                        'score'        => 4,
                        'confidence'   => 0.8,
                        'reasoning'    => 'Good rapport.',
                        'evidence'     => [['segment_code' => 'S1', 'quote' => 'Good morning']],
                    ]]],
                ]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 20],
            ]),
        ]);

        app(ClaudeScoringService::class)->score($call->fresh());

        $result = $call->criterionResults()->first();
        $this->assertEqualsWithDelta(0.75, $result->ai_score, 0.001); // (4-1)/4 = 0.75
    }
}
