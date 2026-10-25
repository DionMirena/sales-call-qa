<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletedTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** Deleting a template must not break the calls that were scored with it. */
    public function test_calls_still_work_after_their_template_is_deleted(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        $agent    = Agent::factory()->create();
        $template = Template::factory()->create(['created_by' => $admin->id, 'name' => 'Old Template']);

        $call = Call::create([
            'agent_id'          => $agent->id,
            'template_id'       => $template->id,
            'uploaded_by'       => $admin->id,
            'original_path'     => 'calls/originals/x.mp3',
            'original_filename' => 'x.mp3',
            'status'            => 'done',
            'score_percent'     => 60.0,
            'outcome'           => 'fail',
            'agent_speaker'     => 0,
        ]);
        $result = $call->criterionResults()->create([
            'criterion_name' => 'Greeting', 'criterion_description' => 'Test', 'criterion_type' => 'pass_fail',
            'criterion_weight' => 1, 'criterion_is_critical' => false, 'ai_result' => 'fail', 'ai_score' => 0.0,
            'confidence' => 0.4, 'reasoning' => 'Test', 'evidence' => [], 'verification_status' => 'unverified',
            'needs_review' => true,
        ]);

        $this->actingAs($admin)->delete(route('templates.destroy', $template))->assertRedirect();
        $this->assertSoftDeleted($template);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Old Template');
        $this->actingAs($admin)->get(route('calls.index'))->assertOk()->assertSee('Old Template');
        $this->actingAs($admin)->get(route('calls.show', $call))->assertOk()->assertSee('Old Template');
        $this->actingAs($admin)->get(route('agents.show', $agent))->assertOk();

        // An override still recalculates the score with the deleted template's pass threshold.
        $this->actingAs($admin)
            ->post(route('overrides.store', $result), ['new_result' => 'pass', 'reason' => 'Checked the recording again.'])
            ->assertRedirect(route('calls.show', $call));
        $this->assertSame('pass', $call->fresh()->outcome);
    }
}
