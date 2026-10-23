<?php

namespace Tests\Unit;

use App\Models\Agent;
use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use App\Models\User;
use App\Support\ScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Call $call;
    private Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        $user          = User::factory()->create(['role' => 'manager']);
        $agent         = Agent::factory()->create();
        $this->template = Template::factory()->create([
            'created_by'     => $user->id,
            'pass_threshold' => 80,
        ]);
        $this->call = Call::create([
            'agent_id'          => $agent->id,
            'template_id'       => $this->template->id,
            'uploaded_by'       => $user->id,
            'original_path'     => 'calls/test.mp3',
            'original_filename' => 'test.mp3',
            'status'            => 'scoring',
        ]);
    }

    private function makeResult(array $attrs): CriterionResult
    {
        return $this->call->criterionResults()->create(array_merge([
            'criterion_name'        => 'Test',
            'criterion_description' => 'Test',
            'criterion_weight'      => 1,
            'criterion_is_critical' => false,
            'confidence'            => 0.9,
            'reasoning'             => 'Test',
            'evidence'              => [],
            'verification_status'   => 'verified',
            'needs_review'          => false,
        ], $attrs));
    }

    // ── Basic pass/fail ───────────────────────────────────────────────────────

    public function test_all_pass_gives_100_percent(): void
    {
        $results = collect([
            $this->makeResult(['criterion_type' => 'pass_fail', 'ai_result' => 'pass']),
            $this->makeResult(['criterion_type' => 'pass_fail', 'ai_result' => 'pass']),
        ]);

        [$score, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals(100.0, $score);
        $this->assertEquals('pass', $outcome);
    }

    public function test_all_fail_gives_0_percent(): void
    {
        $results = collect([
            $this->makeResult(['criterion_type' => 'pass_fail', 'ai_result' => 'fail']),
        ]);

        [$score, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals(0.0, $score);
        $this->assertEquals('fail', $outcome);
    }

    public function test_weighted_average_is_correct(): void
    {
        // weight 2 pass + weight 1 fail → (2*1 + 1*0) / 3 = 0.666… → 66.67%
        $results = collect([
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_weight' => 2, 'ai_result' => 'pass']),
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_weight' => 1, 'ai_result' => 'fail']),
        ]);

        [$score, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEqualsWithDelta(66.67, $score, 0.01);
        $this->assertEquals('fail', $outcome);
    }

    // ── Outcome threshold ─────────────────────────────────────────────────────

    public function test_score_at_threshold_is_pass(): void
    {
        // 80% exactly — weight 4 pass + weight 1 fail → (4+0)/5 = 80%
        $results = collect([
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_weight' => 4, 'ai_result' => 'pass']),
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_weight' => 1, 'ai_result' => 'fail']),
        ]);

        [, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals('pass', $outcome);
    }

    public function test_score_below_threshold_is_fail(): void
    {
        // 3 pass + 1 fail out of 4 weight → 75% < 80%
        $results = collect([
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_weight' => 3, 'ai_result' => 'pass']),
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_weight' => 1, 'ai_result' => 'fail']),
        ]);

        [, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals('fail', $outcome);
    }

    // ── N/A exclusion ─────────────────────────────────────────────────────────

    public function test_na_excluded_from_denominator(): void
    {
        // 1 pass (weight 1) + 1 na (weight 1) → denominator = 1, score = 100%
        $results = collect([
            $this->makeResult(['criterion_type' => 'yes_no_na', 'ai_result' => 'yes']),
            $this->makeResult(['criterion_type' => 'yes_no_na', 'ai_result' => 'na']),
        ]);

        [$score] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals(100.0, $score);
    }

    // ── scale_1_5 normalization ───────────────────────────────────────────────

    public function test_scale_1_5_normalized_correctly(): void
    {
        // result '3' → (3-1)/4 = 0.5 → 50%
        $results = collect([
            $this->makeResult(['criterion_type' => 'scale_1_5', 'ai_result' => '3']),
        ]);

        [$score] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals(50.0, $score);
    }

    public function test_scale_1_gives_0_percent(): void
    {
        $results = collect([
            $this->makeResult(['criterion_type' => 'scale_1_5', 'ai_result' => '1']),
        ]);

        [$score] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals(0.0, $score);
    }

    public function test_scale_5_gives_100_percent(): void
    {
        $results = collect([
            $this->makeResult(['criterion_type' => 'scale_1_5', 'ai_result' => '5']),
        ]);

        [$score] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals(100.0, $score);
    }

    // ── extract type ──────────────────────────────────────────────────────────

    public function test_extract_type_excluded_from_score(): void
    {
        // One extract (excluded) + one pass → score = 100%
        $results = collect([
            $this->makeResult(['criterion_type' => 'extract',   'ai_result' => '£34/month']),
            $this->makeResult(['criterion_type' => 'pass_fail', 'ai_result' => 'pass']),
        ]);

        [$score] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals(100.0, $score);
    }

    // ── auto_fail ─────────────────────────────────────────────────────────────

    public function test_critical_fail_triggers_auto_fail(): void
    {
        $results = collect([
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_is_critical' => true,  'ai_result' => 'fail']),
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_is_critical' => false, 'ai_result' => 'pass']),
        ]);

        [, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals('auto_fail', $outcome);
    }

    public function test_critical_no_on_yes_no_na_triggers_auto_fail(): void
    {
        $results = collect([
            $this->makeResult(['criterion_type' => 'yes_no_na', 'criterion_is_critical' => true, 'ai_result' => 'no']),
        ]);

        [, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertEquals('auto_fail', $outcome);
    }

    public function test_non_critical_fail_does_not_trigger_auto_fail(): void
    {
        $results = collect([
            $this->makeResult(['criterion_type' => 'pass_fail', 'criterion_is_critical' => false, 'ai_result' => 'fail']),
        ]);

        [, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertNotEquals('auto_fail', $outcome);
    }

    // ── Edge case ─────────────────────────────────────────────────────────────

    public function test_all_extract_or_na_returns_null_score(): void
    {
        $results = collect([
            $this->makeResult(['criterion_type' => 'extract',   'ai_result' => 'text']),
            $this->makeResult(['criterion_type' => 'yes_no_na', 'ai_result' => 'na']),
        ]);

        [$score, $outcome] = ScoreCalculator::calculate($this->template, $results);

        $this->assertNull($score);
        $this->assertEquals('pass', $outcome);
    }
}
