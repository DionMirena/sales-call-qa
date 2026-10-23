<?php

namespace Tests\Feature;

use App\Jobs\ProcessCallJob;
use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteCallTest extends TestCase
{
    use RefreshDatabase;

    private function makeCall(User $manager, array $attrs = []): Call
    {
        $agent = Agent::factory()->create();
        $tpl   = Template::factory()->create(['created_by' => $manager->id]);

        return Call::create(array_merge([
            'agent_id'          => $agent->id,
            'template_id'       => $tpl->id,
            'uploaded_by'       => $manager->id,
            'original_path'     => 'calls/originals/test.mp3',
            'original_filename' => 'test.mp3',
            'status'            => 'done',
            'score_percent'     => 80.0,
            'outcome'           => 'pass',
            'agent_speaker'     => 0,
        ], $attrs));
    }

    // ── Auth / policy ─────────────────────────────────────────────────────────

    public function test_delete_requires_auth(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $call = $this->makeCall($manager);

        $this->delete(route('calls.destroy', $call))->assertRedirect('/login');
    }

    public function test_manager_can_delete_own_call(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('calls/originals/test.mp3', 'audio');

        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->makeCall($manager, ['original_path' => 'calls/originals/test.mp3']);

        $this->actingAs($manager)
            ->delete(route('calls.destroy', $call))
            ->assertRedirect(route('calls.index'));

        $this->assertDatabaseMissing('calls', ['id' => $call->id]);
    }

    public function test_other_manager_cannot_delete(): void
    {
        $manager  = User::factory()->create(['role' => 'manager']);
        $stranger = User::factory()->create(['role' => 'manager']);
        $call     = $this->makeCall($manager);

        $this->actingAs($stranger)
            ->delete(route('calls.destroy', $call))
            ->assertForbidden();

        $this->assertDatabaseHas('calls', ['id' => $call->id]);
    }

    public function test_admin_can_delete_any_call(): void
    {
        Storage::fake('private');
        $admin   = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->makeCall($manager);

        $this->actingAs($admin)
            ->delete(route('calls.destroy', $call))
            ->assertRedirect(route('calls.index'));

        $this->assertDatabaseMissing('calls', ['id' => $call->id]);
    }

    // ── File deletion ─────────────────────────────────────────────────────────

    public function test_delete_removes_original_and_audio_files(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('calls/originals/test.mp3', 'original audio');
        Storage::disk('private')->put('calls/1/audio.mp3', 'normalised audio');

        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->makeCall($manager, [
            'original_path' => 'calls/originals/test.mp3',
            'audio_path'    => 'calls/1/audio.mp3',
        ]);

        $this->actingAs($manager)->delete(route('calls.destroy', $call));

        Storage::disk('private')->assertMissing('calls/originals/test.mp3');
        Storage::disk('private')->assertMissing('calls/1/audio.mp3');
    }

    public function test_delete_with_no_audio_path_does_not_error(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('calls/originals/test.mp3', 'original');

        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->makeCall($manager, [
            'original_path' => 'calls/originals/test.mp3',
            'audio_path'    => null,
            'status'        => 'failed',
            'score_percent' => null,
            'outcome'       => null,
        ]);

        $this->actingAs($manager)
            ->delete(route('calls.destroy', $call))
            ->assertRedirect(route('calls.index'));
    }

    // ── Cascade deletion ──────────────────────────────────────────────────────

    public function test_delete_cascades_to_segments_and_results(): void
    {
        Storage::fake('private');
        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->makeCall($manager);

        TranscriptSegment::insert([
            'call_id' => $call->id, 'code' => 'S1', 'speaker' => 0,
            'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Hello',
        ]);

        $result = $call->criterionResults()->create([
            'criterion_name' => 'Test', 'criterion_description' => 'Test',
            'criterion_type' => 'pass_fail', 'criterion_weight' => 1,
            'criterion_is_critical' => false, 'ai_result' => 'pass',
            'ai_score' => 1.0, 'confidence' => 0.9, 'reasoning' => 'ok',
            'evidence' => [], 'verification_status' => 'verified',
        ]);
        $result->overrides()->create([
            'user_id' => $manager->id, 'old_result' => 'pass', 'old_score' => 1.0,
            'new_result' => 'fail', 'new_score' => 0.0,
            'reason' => 'Actually failed on closer review.',
        ]);

        $this->actingAs($manager)->delete(route('calls.destroy', $call));

        $this->assertDatabaseMissing('transcript_segments', ['call_id' => $call->id]);
        $this->assertDatabaseMissing('criterion_results', ['call_id' => $call->id]);
        $this->assertDatabaseMissing('overrides', ['criterion_result_id' => $result->id]);
    }

    // ── Re-score ──────────────────────────────────────────────────────────────

    public function test_rescore_requires_auth(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $call = $this->makeCall($manager);
        $this->post(route('calls.rescore', $call))->assertRedirect('/login');
    }

    public function test_rescore_clears_results_and_requeues(): void
    {
        Queue::fake(); // prevent actual job execution (ffmpeg, API calls)

        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->makeCall($manager);

        $result = $call->criterionResults()->create([
            'criterion_name' => 'Test', 'criterion_description' => 'Test',
            'criterion_type' => 'pass_fail', 'criterion_weight' => 1,
            'criterion_is_critical' => false, 'ai_result' => 'pass',
            'ai_score' => 1.0, 'confidence' => 0.9, 'reasoning' => 'ok',
            'evidence' => [], 'verification_status' => 'verified',
        ]);

        $this->actingAs($manager)
            ->post(route('calls.rescore', $call))
            ->assertRedirect(route('calls.show', $call));

        $call->refresh();
        $this->assertEquals('queued', $call->status);
        $this->assertNull($call->score_percent);
        $this->assertDatabaseMissing('criterion_results', ['id' => $result->id]);
        Queue::assertPushed(ProcessCallJob::class, fn ($job) => $job->callId === $call->id);
    }

    public function test_rescore_only_allowed_on_done_calls(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->makeCall($manager, ['status' => 'failed', 'score_percent' => null, 'outcome' => null]);

        $this->actingAs($manager)
            ->post(route('calls.rescore', $call))
            ->assertStatus(422);
    }
}
