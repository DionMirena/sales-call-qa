<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaStatsTest extends TestCase
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

    public function test_stats_runs_with_no_calls(): void
    {
        $this->artisan('qa:stats')
            ->assertSuccessful()
            ->expectsOutputToContain('QA Stats');
    }

    public function test_stats_shows_total_call_count(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->makeCall($manager);
        $this->makeCall($manager);
        $this->makeCall($manager, ['outcome' => 'fail', 'score_percent' => 50.0]);

        $this->artisan('qa:stats', ['--days' => '0'])
            ->assertSuccessful()
            ->expectsOutputToContain('3');
    }

    public function test_stats_shows_pass_rate(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->makeCall($manager, ['outcome' => 'pass', 'score_percent' => 90.0]);
        $this->makeCall($manager, ['outcome' => 'pass', 'score_percent' => 90.0]);
        $this->makeCall($manager, ['outcome' => 'fail', 'score_percent' => 40.0]);

        $this->artisan('qa:stats', ['--days' => '0'])
            ->assertSuccessful()
            ->expectsOutputToContain('67%'); // 2/3 = 66.7% rounded
    }

    public function test_stats_errors_on_unknown_template(): void
    {
        $this->artisan('qa:stats', ['--template' => 'Nonexistent'])
            ->assertFailed()
            ->expectsOutputToContain('Template not found');
    }

    public function test_stats_filters_by_template(): void
    {
        $manager  = User::factory()->create(['role' => 'manager']);

        $agent    = Agent::factory()->create();
        $tplA     = Template::factory()->create(['created_by' => $manager->id, 'name' => 'Template A']);
        $tplB     = Template::factory()->create(['created_by' => $manager->id, 'name' => 'Template B']);

        // 2 calls on Template A, 1 on Template B
        foreach (range(1, 2) as $_) {
            Call::create([
                'agent_id' => $agent->id, 'template_id' => $tplA->id, 'uploaded_by' => $manager->id,
                'original_path' => 'x', 'original_filename' => 'x',
                'status' => 'done', 'score_percent' => 80.0, 'outcome' => 'pass', 'agent_speaker' => 0,
            ]);
        }
        Call::create([
            'agent_id' => $agent->id, 'template_id' => $tplB->id, 'uploaded_by' => $manager->id,
            'original_path' => 'x', 'original_filename' => 'x',
            'status' => 'done', 'score_percent' => 50.0, 'outcome' => 'fail', 'agent_speaker' => 0,
        ]);

        $this->artisan('qa:stats', ['--template' => 'Template A', '--days' => '0'])
            ->assertSuccessful()
            ->expectsOutputToContain('Template A');
    }

    public function test_stats_shows_top_failed_criteria(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $call    = $this->makeCall($manager, ['outcome' => 'fail', 'score_percent' => 30.0]);

        $call->criterionResults()->create([
            'criterion_name'        => 'Greeting',
            'criterion_description' => 'Test',
            'criterion_type'        => 'pass_fail',
            'criterion_weight'      => 1,
            'criterion_is_critical' => false,
            'ai_result'             => 'fail',
            'ai_score'              => 0.0,
            'confidence'            => 0.9,
            'reasoning'             => 'No greeting',
            'evidence'              => [],
            'verification_status'   => 'verified',
        ]);

        $this->artisan('qa:stats', ['--days' => '0'])
            ->assertSuccessful()
            ->expectsOutputToContain('Greeting');
    }

    public function test_stats_shows_override_rate_per_criterion(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $result  = fn (Call $call, string $name, string $ai) => $call->criterionResults()->create([
            'criterion_name'        => $name,
            'criterion_description' => 'Test',
            'criterion_type'        => 'pass_fail',
            'criterion_weight'      => 1,
            'criterion_is_critical' => false,
            'ai_result'             => $ai,
            'ai_score'              => $ai === 'pass' ? 1.0 : 0.0,
            'confidence'            => 0.9,
            'reasoning'             => 'Test',
            'evidence'              => [],
            'verification_status'   => 'verified',
        ]);
        $override = fn (CriterionResult $r, string $old, string $new) => $r->overrides()->create([
            'user_id' => $manager->id, 'old_result' => $old, 'new_result' => $new, 'reason' => 'Checked the recording.',
        ]);

        // "Recording notice" scored on 4 calls: 1 changed by a manager, 1 confirmed as correct.
        foreach (range(1, 4) as $i) {
            $r = $result($this->makeCall($manager), 'Recording notice', 'fail');
            if ($i === 1) { $override($r, 'fail', 'pass'); }
            if ($i === 2) { $override($r, 'fail', 'fail'); }
        }
        // "Next steps" scored once and never overridden.
        $result($this->makeCall($manager), 'Next steps', 'pass');

        $this->artisan('qa:stats', ['--days' => '0'])
            ->assertSuccessful()
            ->expectsOutputToContain('Override rate per criterion')
            ->expectsTable(
                ['Criterion', 'Scored', 'Reviewed', 'Changed', 'Change rate'],
                [
                    ['Recording notice', 4, 2, 1, '25%'],
                    ['Next steps', 1, 0, 0, '0%'],
                ]
            );
    }

    public function test_stats_respects_days_filter(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        // Recent call
        $this->makeCall($manager);

        // Old call — backdate via raw query
        $old = $this->makeCall($manager);
        \Illuminate\Support\Facades\DB::table('calls')
            ->where('id', $old->id)
            ->update(['updated_at' => now()->subDays(60)]);

        // With --days=30, only the recent call should count
        $this->artisan('qa:stats', ['--days' => '30'])
            ->assertSuccessful()
            ->expectsOutputToContain('1');
    }
}
