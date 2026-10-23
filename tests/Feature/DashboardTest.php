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

class DashboardTest extends TestCase
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

    private function addNeedsReviewResult(Call $call): CriterionResult
    {
        return $call->criterionResults()->create([
            'criterion_name'        => 'Test',
            'criterion_description' => 'Test',
            'criterion_type'        => 'pass_fail',
            'criterion_weight'      => 1,
            'criterion_is_critical' => false,
            'ai_result'             => 'fail',
            'ai_score'              => 0.0,
            'confidence'            => 0.4,
            'reasoning'             => 'Low confidence',
            'evidence'              => [],
            'verification_status'   => 'unverified',
            'needs_review'          => true,
        ]);
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get(route('dashboard'))->assertRedirect('/login');
    }

    public function test_today_done_count_reflects_calls_processed_today(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->makeCall($manager);
        $this->makeCall($manager);

        // A call from yesterday — bypass Eloquent so the auto-update doesn't overwrite it
        $old = $this->makeCall($manager);
        DB::table('calls')->where('id', $old->id)->update(['updated_at' => now()->subDay()]);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $this->assertEquals(2, $response->viewData('todayDone'));
    }

    public function test_manager_today_count_excludes_other_managers_calls(): void
    {
        $manager  = User::factory()->create(['role' => 'manager']);
        $stranger = User::factory()->create(['role' => 'manager']);

        $this->makeCall($manager);
        $this->makeCall($stranger);

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $this->assertEquals(1, $response->viewData('todayDone'));
    }

    public function test_needs_review_count_is_correct(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $call1 = $this->makeCall($manager);
        $call2 = $this->makeCall($manager);
        $call3 = $this->makeCall($manager); // no needs_review

        $this->addNeedsReviewResult($call1);
        $this->addNeedsReviewResult($call2);

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $this->assertEquals(2, $response->viewData('needsReviewCount'));
    }

    public function test_needs_review_queue_shows_correct_calls(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $call1 = $this->makeCall($manager);
        $call2 = $this->makeCall($manager);
        $noReview = $this->makeCall($manager);

        $this->addNeedsReviewResult($call1);
        $this->addNeedsReviewResult($call2);

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $queue = $response->viewData('needsReviewCalls');

        $this->assertCount(2, $queue);
        $ids = $queue->pluck('id')->toArray();
        $this->assertContains($call1->id, $ids);
        $this->assertContains($call2->id, $ids);
        $this->assertNotContains($noReview->id, $ids);
    }

    public function test_needs_review_count_reflects_withcount(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $call = $this->makeCall($manager);
        $this->addNeedsReviewResult($call);
        $this->addNeedsReviewResult($call);

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $queue = $response->viewData('needsReviewCalls');

        $this->assertEquals(2, $queue->first()->needs_review_count);
    }
}
