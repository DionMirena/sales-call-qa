<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentStatsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCall(Agent $agent, User $manager, array $attrs = []): Call
    {
        $tpl = Template::factory()->create(['created_by' => $manager->id]);

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

    private function addResult(Call $call, array $attrs = []): CriterionResult
    {
        return $call->criterionResults()->create(array_merge([
            'criterion_name'        => 'Greeting',
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
        ], $attrs));
    }

    public function test_manager_only_sees_their_own_calls_on_agent_pages(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $other   = User::factory()->create(['role' => 'manager']);
        $admin   = User::factory()->create(['role' => 'admin']);
        $agent   = Agent::factory()->create();

        $own     = $this->makeCall($agent, $manager, ['score_percent' => 90.0]);
        $foreign = $this->makeCall($agent, $other, ['score_percent' => 20.0, 'outcome' => 'auto_fail']);

        $show = $this->actingAs($manager)->get(route('agents.show', $agent))->assertOk();
        $this->assertEquals(1, $show->viewData('totalDone'));
        $this->assertEquals(90.0, $show->viewData('avgScore'));
        $this->assertSame([$own->id], $show->viewData('recentCalls')->pluck('id')->all());

        $index = $this->actingAs($manager)->get(route('agents.index'))->assertOk();
        $this->assertEquals(1, $index->viewData('agents')->firstWhere('id', $agent->id)->total_calls_count);

        // Admins still see every call.
        $adminShow = $this->actingAs($admin)->get(route('agents.show', $agent));
        $this->assertEquals(2, $adminShow->viewData('totalDone'));
        $this->assertContains($foreign->id, $adminShow->viewData('recentCalls')->pluck('id')->all());
    }

    public function test_agent_show_requires_auth(): void
    {
        $agent = Agent::factory()->create();
        $this->get(route('agents.show', $agent))->assertRedirect('/login');
    }

    public function test_agent_show_displays_total_and_avg_score(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        $this->makeCall($agent, $manager, ['score_percent' => 60.0, 'outcome' => 'fail']);
        $this->makeCall($agent, $manager, ['score_percent' => 80.0, 'outcome' => 'pass']);

        $response = $this->actingAs($manager)->get(route('agents.show', $agent));

        $response->assertOk();
        $this->assertEquals(2, $response->viewData('totalDone'));
        $this->assertEquals(70.0, $response->viewData('avgScore'));
    }

    public function test_pass_rate_is_correct(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        $this->makeCall($agent, $manager, ['score_percent' => 90, 'outcome' => 'pass']);
        $this->makeCall($agent, $manager, ['score_percent' => 50, 'outcome' => 'fail']);
        $this->makeCall($agent, $manager, ['score_percent' => 50, 'outcome' => 'fail']);
        $this->makeCall($agent, $manager, ['score_percent' => 50, 'outcome' => 'fail']);

        $response = $this->actingAs($manager)->get(route('agents.show', $agent));
        $this->assertEquals(25, $response->viewData('passRate'));
    }

    public function test_top_failed_criteria_use_effective_results(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        $call = $this->makeCall($agent, $manager, ['score_percent' => 50, 'outcome' => 'fail']);

        // AI says fail for "Disclosure" — but manager overrides to pass
        $r1 = $this->addResult($call, ['criterion_name' => 'Disclosure', 'ai_result' => 'fail']);
        $r1->overrides()->create([
            'user_id'    => $manager->id,
            'old_result' => 'fail',
            'old_score'  => 0.0,
            'new_result' => 'pass',
            'new_score'  => 1.0,
            'reason'     => 'Agent did disclose, AI was wrong.',
        ]);

        // AI says fail for "Greeting" — no override
        $this->addResult($call, ['criterion_name' => 'Greeting', 'ai_result' => 'fail']);

        $response = $this->actingAs($manager)->get(route('agents.show', $agent));

        $failCounts = $response->viewData('failCounts');

        // Greeting should appear (still fail), Disclosure should NOT (overridden to pass)
        $this->assertArrayHasKey('Greeting', $failCounts);
        $this->assertArrayNotHasKey('Disclosure', $failCounts);
    }

    public function test_fail_counts_are_sorted_descending(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        // 2 calls — "Closing" fails twice, "Greeting" fails once
        foreach (range(1, 2) as $i) {
            $call = $this->makeCall($agent, $manager, ['score_percent' => 50, 'outcome' => 'fail']);
            $this->addResult($call, ['criterion_name' => 'Closing', 'ai_result' => 'fail']);
        }

        $call2 = $this->makeCall($agent, $manager, ['score_percent' => 50, 'outcome' => 'fail']);
        $this->addResult($call2, ['criterion_name' => 'Greeting', 'ai_result' => 'fail']);

        $response = $this->actingAs($manager)->get(route('agents.show', $agent));

        $keys = array_keys($response->viewData('failCounts'));
        $this->assertEquals('Closing', $keys[0]);
        $this->assertEquals('Greeting', $keys[1]);
    }

    public function test_agent_with_no_calls_shows_null_stats(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        $response = $this->actingAs($manager)->get(route('agents.show', $agent));

        $response->assertOk();
        $this->assertEquals(0, $response->viewData('totalDone'));
        $this->assertNull($response->viewData('avgScore'));
        $this->assertNull($response->viewData('passRate'));
        $this->assertEmpty($response->viewData('failCounts'));
    }

    public function test_agents_index_lists_agents(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Agent::factory()->count(3)->create();

        $response = $this->actingAs($manager)->get(route('agents.index'));
        $response->assertOk();
        $this->assertCount(3, $response->viewData('agents'));
    }
}
