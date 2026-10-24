<?php

namespace Tests\Feature;

use App\Jobs\ProcessCallJob;
use App\Models\Agent;
use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RescoreTest extends TestCase
{
    use RefreshDatabase;

    private function doneCall(User $user, ?Template $tpl = null): Call
    {
        $agent = Agent::factory()->create();
        $tpl ??= Template::factory()->create(['created_by' => $user->id]);

        $call = Call::create([
            'agent_id'          => $agent->id,
            'template_id'       => $tpl->id,
            'uploaded_by'       => $user->id,
            'original_path'     => 'calls/originals/test.mp3',
            'original_filename' => 'test.mp3',
            'status'            => 'done',
            'score_percent'     => 80.0,
            'outcome'           => 'pass',
            'agent_speaker'     => 0,
        ]);

        TranscriptSegment::insert([
            ['call_id' => $call->id, 'code' => 'S1', 'speaker' => 0, 'start_ms' => 0, 'end_ms' => 3000, 'text' => 'Hello'],
        ]);

        $call->criterionResults()->create([
            'criterion_name'        => 'Test',
            'criterion_description' => 'Test',
            'criterion_type'        => 'pass_fail',
            'criterion_weight'      => 1,
            'criterion_is_critical' => false,
            'ai_result'             => 'pass',
            'ai_score'              => 1.0,
            'confidence'            => 0.9,
            'reasoning'             => 'Good',
            'evidence'              => [],
            'verification_status'   => 'verified',
            'needs_review'          => false,
        ]);

        return $call;
    }

    public function test_rescore_keeps_segments(): void
    {
        Queue::fake();
        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->doneCall($manager);

        $this->assertEquals(1, $call->segments()->count());

        $this->actingAs($manager)->post(route('calls.rescore', $call))->assertRedirect();

        $this->assertEquals(1, $call->segments()->count(), 'Segments must be kept during re-score');
    }

    public function test_rescore_deletes_criterion_results(): void
    {
        Queue::fake();
        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->doneCall($manager);

        $this->assertEquals(1, $call->criterionResults()->count());

        $this->actingAs($manager)->post(route('calls.rescore', $call));

        $this->assertEquals(0, $call->criterionResults()->count(), 'Criterion results must be cleared');
    }

    public function test_rescore_dispatches_rescore_only_job(): void
    {
        Queue::fake();
        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->doneCall($manager);

        $this->actingAs($manager)->post(route('calls.rescore', $call));

        Queue::assertPushed(ProcessCallJob::class, function (ProcessCallJob $job) use ($call) {
            return $job->callId === $call->id && $job->rescoreOnly === true;
        });
    }

    public function test_rescore_with_template_id_changes_template(): void
    {
        Queue::fake();
        $manager  = User::factory()->create(['role' => 'manager']);
        $call     = $this->doneCall($manager);
        $newTpl   = Template::factory()->create(['created_by' => $manager->id]);

        $this->actingAs($manager)
            ->post(route('calls.rescore', $call), ['template_id' => $newTpl->id]);

        $this->assertEquals($newTpl->id, $call->fresh()->template_id);
    }

    public function test_rescore_with_invalid_template_id_fails(): void
    {
        Queue::fake();
        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->doneCall($manager);

        $this->actingAs($manager)
            ->post(route('calls.rescore', $call), ['template_id' => 9999])
            ->assertSessionHasErrors('template_id');
    }

    public function test_rescore_only_flag_skips_normalise_and_transcribe(): void
    {
        $call = Call::create([
            'agent_id'          => Agent::factory()->create()->id,
            'template_id'       => Template::factory()->create([
                'created_by' => User::factory()->create()->id,
            ])->id,
            'uploaded_by'       => User::factory()->create()->id,
            'original_path'     => '_test',
            'original_filename' => '_test',
            'status'            => 'queued',
            'agent_speaker'     => 0,
        ]);

        TranscriptSegment::insert([
            ['call_id' => $call->id, 'code' => 'S1', 'speaker' => 0, 'start_ms' => 0, 'end_ms' => 1000, 'text' => 'Hello'],
        ]);

        $normaliser    = $this->mock(\App\Support\AudioNormaliser::class);
        $transcription = $this->mock(\App\Services\TranscriptionService::class);
        $scoring       = $this->mock(\App\Services\ScoringService::class);

        // Neither normalise nor transcribe should be called
        $normaliser->shouldNotReceive('normalise');
        $transcription->shouldNotReceive('transcribe');
        $scoring->shouldReceive('score')->once();

        (new ProcessCallJob($call->id, rescoreOnly: true))->handle($normaliser, $transcription, $scoring);
    }
}
