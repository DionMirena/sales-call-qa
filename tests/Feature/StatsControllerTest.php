<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCall(User $uploader, Agent $agent, array $attrs = []): Call
    {
        $tpl = Template::factory()->create(['created_by' => $uploader->id]);

        return Call::create(array_merge([
            'agent_id'          => $agent->id,
            'template_id'       => $tpl->id,
            'uploaded_by'       => $uploader->id,
            'original_path'     => 'calls/originals/test.mp3',
            'original_filename' => 'test.mp3',
            'status'            => 'done',
            'score_percent'     => 80.0,
            'outcome'           => 'pass',
            'agent_speaker'     => 0,
        ], $attrs));
    }

    private function addFailResult(Call $call, string $criterionName = 'Greeting'): CriterionResult
    {
        return $call->criterionResults()->create([
            'criterion_name'        => $criterionName,
            'criterion_description' => 'Test',
            'criterion_type'        => 'pass_fail',
            'criterion_weight'      => 1,
            'criterion_is_critical' => false,
            'ai_result'             => 'fail',
            'ai_score'              => 0.0,
            'confidence'            => 0.9,
            'reasoning'             => 'Not found',
            'evidence'              => [],
            'verification_status'   => 'verified',
            'needs_review'          => false,
        ]);
    }

    public function test_stats_page_requires_auth(): void
    {
        $this->get(route('stats.index'))->assertRedirect('/login');
    }

    public function test_manager_can_see_stats_page(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->get(route('stats.index'))->assertOk();
    }

    public function test_admin_can_see_stats_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('stats.index'))->assertOk();
    }

    public function test_empty_state_when_no_done_calls(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)->get(route('stats.index'));
        $response->assertOk();
        $this->assertEquals(0, $response->viewData('totalDone'));
    }

    public function test_outcomes_data_counts_correctly(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        $this->makeCall($manager, $agent, ['outcome' => 'pass']);
        $this->makeCall($manager, $agent, ['outcome' => 'pass']);
        $this->makeCall($manager, $agent, ['outcome' => 'fail']);
        $this->makeCall($manager, $agent, ['outcome' => 'auto_fail']);

        $response = $this->actingAs($manager)->get(route('stats.index'));
        $outcomes = $response->viewData('outcomesData');

        $this->assertEquals(2, $outcomes['pass']);
        $this->assertEquals(1, $outcomes['fail']);
        $this->assertEquals(1, $outcomes['auto_fail']);
    }

    public function test_manager_only_sees_own_calls_in_outcomes(): void
    {
        $manager  = User::factory()->create(['role' => 'manager']);
        $stranger = User::factory()->create(['role' => 'manager']);
        $agent    = Agent::factory()->create();

        $this->makeCall($manager,  $agent, ['outcome' => 'pass']);
        $this->makeCall($stranger, $agent, ['outcome' => 'fail']);   // should be invisible

        $response = $this->actingAs($manager)->get(route('stats.index'));
        $outcomes = $response->viewData('outcomesData');

        $this->assertEquals(1, $outcomes['pass']);
        $this->assertEquals(0, $outcomes['fail']);
        $this->assertEquals(1, $response->viewData('totalDone'));
    }

    public function test_admin_sees_all_calls(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        $manager1 = User::factory()->create(['role' => 'manager']);
        $manager2 = User::factory()->create(['role' => 'manager']);
        $agent    = Agent::factory()->create();

        $this->makeCall($manager1, $agent, ['outcome' => 'pass']);
        $this->makeCall($manager2, $agent, ['outcome' => 'fail']);

        $response = $this->actingAs($admin)->get(route('stats.index'));
        $this->assertEquals(2, $response->viewData('totalDone'));
    }

    public function test_calls_per_agent_data(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent1  = Agent::factory()->create(['name' => 'Alice']);
        $agent2  = Agent::factory()->create(['name' => 'Bob']);

        $this->makeCall($manager, $agent1);
        $this->makeCall($manager, $agent1);
        $this->makeCall($manager, $agent2);

        $response = $this->actingAs($manager)->get(route('stats.index'));
        $agentsData = $response->viewData('agentsData');

        $byName = collect($agentsData)->keyBy('name');
        $this->assertEquals(2, $byName['Alice']['count']);
        $this->assertEquals(1, $byName['Bob']['count']);
    }

    public function test_avg_score_per_agent_data(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create(['name' => 'Alice']);

        $this->makeCall($manager, $agent, ['score_percent' => 90.0]);
        $this->makeCall($manager, $agent, ['score_percent' => 70.0]);

        $response   = $this->actingAs($manager)->get(route('stats.index'));
        $scoresData = $response->viewData('agentScoresData');

        $this->assertCount(1, $scoresData);
        $this->assertEquals('Alice', $scoresData[0]['name']);
        $this->assertEquals(80.0, $scoresData[0]['avg']);
    }

    public function test_most_failed_criteria_data(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        $call1 = $this->makeCall($manager, $agent);
        $call2 = $this->makeCall($manager, $agent);

        $this->addFailResult($call1, 'Recording Notice');
        $this->addFailResult($call2, 'Recording Notice');
        $this->addFailResult($call1, 'Cooling Off');

        $response  = $this->actingAs($manager)->get(route('stats.index'));
        $criteria  = $response->viewData('failedCriteriaData');

        $byName = collect($criteria)->keyBy('name');
        $this->assertEquals(2, $byName['Recording Notice']['count']);
        $this->assertEquals(1, $byName['Cooling Off']['count']);
    }

    public function test_failed_criteria_excludes_other_managers_calls(): void
    {
        $manager  = User::factory()->create(['role' => 'manager']);
        $stranger = User::factory()->create(['role' => 'manager']);
        $agent    = Agent::factory()->create();

        $myCall    = $this->makeCall($manager,  $agent);
        $theirCall = $this->makeCall($stranger, $agent);

        $this->addFailResult($myCall,    'Greeting');
        $this->addFailResult($theirCall, 'Greeting'); // should not appear for $manager

        $response = $this->actingAs($manager)->get(route('stats.index'));
        $criteria = $response->viewData('failedCriteriaData');

        $this->assertCount(1, $criteria);
        $this->assertEquals(1, $criteria[0]['count']);
    }

    public function test_score_trend_returns_21_days(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $response  = $this->actingAs($manager)->get(route('stats.index'));
        $trendData = $response->viewData('trendData');

        $this->assertCount(21, $trendData);
    }

    public function test_score_trend_fills_nulls_for_days_with_no_data(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        // Only one call today
        $this->makeCall($manager, $agent, ['score_percent' => 90.0]);

        $response  = $this->actingAs($manager)->get(route('stats.index'));
        $trendData = $response->viewData('trendData');

        $nullDays = collect($trendData)->filter(fn($d) => $d['avg'] === null);
        $this->assertGreaterThan(0, $nullDays->count());

        // Today's entry should have avg = 90
        $today = collect($trendData)->last();
        $this->assertEquals(90.0, $today['avg']);
    }

    public function test_non_done_calls_excluded_from_stats(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();

        $this->makeCall($manager, $agent, ['status' => 'queued', 'outcome' => null, 'score_percent' => null]);
        $this->makeCall($manager, $agent, ['status' => 'failed', 'outcome' => null, 'score_percent' => null]);

        $response = $this->actingAs($manager)->get(route('stats.index'));
        $this->assertEquals(0, $response->viewData('totalDone'));
        $this->assertEmpty($response->viewData('agentsData'));
    }
}
