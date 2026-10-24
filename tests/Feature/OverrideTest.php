<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverrideTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeFixtures(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $agent   = Agent::factory()->create();
        $tpl     = Template::factory()->create([
            'created_by'     => $manager->id,
            'pass_threshold' => 80,
        ]);
        $call = Call::create([
            'agent_id'          => $agent->id,
            'template_id'       => $tpl->id,
            'uploaded_by'       => $manager->id,
            'original_path'     => 'calls/t.mp3',
            'original_filename' => 't.mp3',
            'status'            => 'done',
            'score_percent'     => 50.0,
            'outcome'           => 'fail',
            'agent_speaker'     => 0,
        ]);

        return [$manager, $call, $tpl];
    }

    private function addResult(Call $call, array $attrs = []): CriterionResult
    {
        return $call->criterionResults()->create(array_merge([
            'criterion_name'        => 'Test Criterion',
            'criterion_description' => 'Test',
            'criterion_type'        => 'pass_fail',
            'criterion_weight'      => 1,
            'criterion_is_critical' => false,
            'ai_result'             => 'fail',
            'ai_score'              => 0.0,
            'confidence'            => 0.9,
            'reasoning'             => 'Test reasoning',
            'evidence'              => [],
            'verification_status'   => 'verified',
            'needs_review'          => true,
        ], $attrs));
    }

    private function override(User $user, CriterionResult $result, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post(route('overrides.store', $result), $data);
    }

    // ── Auth / policy ─────────────────────────────────────────────────────────

    public function test_store_requires_auth(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call);

        $this->post(route('overrides.store', $result), [
            'new_result' => 'pass', 'reason' => 'Good enough reason',
        ])->assertRedirect('/login');
    }

    public function test_other_manager_cannot_override(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result   = $this->addResult($call);
        $stranger = User::factory()->create(['role' => 'manager']);

        $this->override($stranger, $result, [
            'new_result' => 'pass', 'reason' => 'Trying to override someone else\'s call.',
        ])->assertForbidden();
    }

    public function test_admin_can_override_any_call(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call);
        $admin  = User::factory()->create(['role' => 'admin']);

        $this->override($admin, $result, [
            'new_result' => 'pass', 'reason' => 'Admin reviewing this call result.',
        ])->assertRedirect();

        $this->assertDatabaseHas('overrides', ['new_result' => 'pass']);
    }

    // ── Stores override ───────────────────────────────────────────────────────

    public function test_override_is_stored_with_correct_fields(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call);

        $this->override($manager, $result, [
            'new_result' => 'pass',
            'reason'     => 'Agent did actually disclose the recording.',
        ])->assertRedirect(route('calls.show', $call));

        $this->assertDatabaseHas('overrides', [
            'criterion_result_id' => $result->id,
            'user_id'             => $manager->id,
            'old_result'          => 'fail',
            'new_result'          => 'pass',
        ]);
    }

    public function test_override_sets_needs_review_false(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call, ['needs_review' => true]);

        $this->override($manager, $result, [
            'new_result' => 'pass', 'reason' => 'Reviewed and corrected the result.',
        ]);

        $this->assertFalse((bool) $result->fresh()->needs_review);
    }

    // ── Score recalculation ───────────────────────────────────────────────────

    public function test_override_recalculates_call_score(): void
    {
        [$manager, $call, $tpl] = $this->makeFixtures();

        // Two criteria: both fail → 0%
        $r1 = $this->addResult($call, ['criterion_weight' => 1, 'ai_result' => 'fail']);
        $r2 = $this->addResult($call, ['criterion_weight' => 1, 'ai_result' => 'fail']);

        // Override r1 to pass → (1+0)/2 = 50%
        $this->override($manager, $r1, [
            'new_result' => 'pass', 'reason' => 'Listened again — clearly passed.',
        ]);

        $call->refresh();
        $this->assertEqualsWithDelta(50.0, $call->score_percent, 0.01);
        $this->assertEquals('fail', $call->outcome);
    }

    public function test_override_to_pass_gives_100_percent_with_one_criterion(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call, ['ai_result' => 'fail']);

        $this->override($manager, $result, [
            'new_result' => 'pass', 'reason' => 'Actually passed after careful review.',
        ]);

        $call->refresh();
        $this->assertEquals(100.0, $call->score_percent);
        $this->assertEquals('pass', $call->outcome);
    }

    public function test_override_critical_criterion_to_fail_triggers_auto_fail(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call, [
            'criterion_is_critical' => true,
            'ai_result'             => 'pass',
        ]);

        $this->override($manager, $result, [
            'new_result' => 'fail', 'reason' => 'Critical criterion was not actually met.',
        ]);

        $call->refresh();
        $this->assertEquals('auto_fail', $call->outcome);
    }

    public function test_overrides_are_append_only_and_latest_wins(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call, ['ai_result' => 'fail']);

        $this->override($manager, $result, [
            'new_result' => 'pass', 'reason' => 'Changed to pass on first review.',
        ]);

        $this->override($manager, $result, [
            'new_result' => 'fail', 'reason' => 'Re-reviewed and reverted back to fail.',
        ]);

        $this->assertCount(2, $result->overrides()->get());
        $this->assertEquals('fail', $result->fresh()->effectiveResult());
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_reason_is_required(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call);

        $this->override($manager, $result, ['new_result' => 'pass', 'reason' => ''])
            ->assertSessionHasErrors('reason', null, "cr_{$result->id}");
    }

    public function test_reason_must_be_at_least_10_chars(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call);

        $this->override($manager, $result, ['new_result' => 'pass', 'reason' => 'Short'])
            ->assertSessionHasErrors('reason', null, "cr_{$result->id}");
    }

    public function test_invalid_result_for_pass_fail_is_rejected(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call, ['criterion_type' => 'pass_fail']);

        $this->override($manager, $result, [
            'new_result' => 'maybe', 'reason' => 'An invalid value for pass_fail type.',
        ])->assertSessionHasErrors('new_result', null, "cr_{$result->id}");
    }

    public function test_valid_scale_result_is_accepted(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call, ['criterion_type' => 'scale_1_5', 'ai_result' => '2']);

        $this->override($manager, $result, [
            'new_result' => '4', 'reason' => 'Rapport was much better than initially scored.',
        ])->assertRedirect();

        $this->assertEquals('4', $result->fresh()->effectiveResult());
    }

    // ── Mark as reviewed ─────────────────────────────────────────────────────

    public function test_mark_as_reviewed_saves_override_with_same_result(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call, ['ai_result' => 'fail', 'needs_review' => true]);

        $this->override($manager, $result, [
            'new_result' => 'fail',
            'reason'     => 'Reviewed: AI result confirmed correct.',
        ])->assertRedirect(route('calls.show', $call));

        $this->assertDatabaseHas('overrides', [
            'criterion_result_id' => $result->id,
            'new_result'          => 'fail',
        ]);
        $this->assertFalse((bool) $result->fresh()->needs_review);
    }

    // ── Normalised score ──────────────────────────────────────────────────────

    public function test_new_score_normalised_for_scale_type(): void
    {
        [$manager, $call] = $this->makeFixtures();
        $result = $this->addResult($call, ['criterion_type' => 'scale_1_5', 'ai_result' => '1']);

        $this->override($manager, $result, [
            'new_result' => '5', 'reason' => 'Excellent rapport throughout the whole call.',
        ]);

        $override = $result->overrides()->latest('created_at')->first();
        $this->assertEqualsWithDelta(1.0, $override->new_score, 0.001); // (5-1)/4 = 1.0
    }
}
