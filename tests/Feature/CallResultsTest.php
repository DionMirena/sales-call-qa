<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallResultsTest extends TestCase
{
    use RefreshDatabase;

    private function doneCall(): Call
    {
        $user  = User::factory()->create(['role' => 'manager']);
        $agent = Agent::factory()->create();
        $tpl   = Template::factory()->create(['created_by' => $user->id]);

        $call = Call::create([
            'agent_id'          => $agent->id,
            'template_id'       => $tpl->id,
            'uploaded_by'       => $user->id,
            'original_path'     => 'calls/orig.mp3',
            'original_filename' => 'orig.mp3',
            'status'            => 'done',
            'outcome'           => 'pass',
            'score_percent'     => 95.0,
            'duration_seconds'  => 153,
            'agent_speaker'     => 0,
            'audio_path'        => 'calls/1/audio.mp3',
        ]);

        TranscriptSegment::insert([
            ['call_id' => $call->id, 'code' => 'S1', 'speaker' => 0, 'start_ms' => 1000,  'end_ms' => 8000,  'text' => 'Hello this is Sarah.'],
            ['call_id' => $call->id, 'code' => 'S2', 'speaker' => 1, 'start_ms' => 8500,  'end_ms' => 10000, 'text' => 'Sure that is fine.'],
        ]);

        return $call;
    }

    // ── show() ───────────────────────────────────────────────────────────────

    public function test_show_requires_auth(): void
    {
        $call = $this->doneCall();
        $this->get(route('calls.show', $call))->assertRedirect('/login');
    }

    public function test_show_returns_view_with_segments(): void
    {
        $call = $this->doneCall();
        $user = $call->uploader()->first() ?? User::factory()->create();

        $this->actingAs($call->uploader)
            ->get(route('calls.show', $call))
            ->assertOk()
            ->assertViewHas('call', fn ($c) => $c->id === $call->id)
            ->assertSee('S1');
    }

    // ── swapSpeaker() ────────────────────────────────────────────────────────

    public function test_swap_speaker_requires_auth(): void
    {
        $call = $this->doneCall();
        $this->post(route('calls.swap_speaker', $call))->assertRedirect('/login');
    }

    public function test_swap_speaker_toggles_agent_speaker(): void
    {
        $call    = $this->doneCall();
        $uploader = User::find($call->uploaded_by);

        $this->actingAs($uploader)
            ->postJson(route('calls.swap_speaker', $call))
            ->assertOk()
            ->assertJson(['agent_speaker' => 1]);

        $this->assertEquals(1, $call->fresh()->agent_speaker);

        // Swap back
        $this->actingAs($uploader)
            ->postJson(route('calls.swap_speaker', $call))
            ->assertOk()
            ->assertJson(['agent_speaker' => 0]);
    }

    public function test_swap_speaker_rejects_non_done_call(): void
    {
        $user  = User::factory()->create(['role' => 'manager']);
        $agent = Agent::factory()->create();
        $tpl   = Template::factory()->create(['created_by' => $user->id]);

        $call = Call::create([
            'agent_id' => $agent->id, 'template_id' => $tpl->id,
            'uploaded_by' => $user->id, 'original_path' => 'x', 'original_filename' => 'x',
            'status' => 'queued',
        ]);

        $this->actingAs($user)
            ->postJson(route('calls.swap_speaker', $call))
            ->assertStatus(422);
    }

    // ── audio() ──────────────────────────────────────────────────────────────

    public function test_audio_route_requires_auth(): void
    {
        $call = $this->doneCall();
        $this->get(route('calls.audio', $call))->assertRedirect('/login');
    }
}
