<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\User;
use App\Services\FakeScoringService;
use App\Services\KeywordScoringService;
use App\Services\ScoringService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaEvalTest extends TestCase
{
    use RefreshDatabase;

    /** The real seeded template, the same one the golden set is labelled against. */
    private function seedRealTemplate(): Template
    {
        User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->seed(TemplateSeeder::class);

        return Template::firstOrFail();
    }

    private function goldenSet(): array
    {
        return json_decode(file_get_contents(base_path('tests/golden/golden_set.json')), true);
    }

    public function test_golden_set_matches_the_seeded_template(): void
    {
        $template = $this->seedRealTemplate();
        $golden   = $this->goldenSet();
        $names    = $template->criteria->pluck('name')->all();

        $this->assertSame($template->name, $golden['template_name']);
        $this->assertGreaterThanOrEqual(3, count($golden['cases']));

        foreach ($golden['cases'] as $case) {
            $this->assertNotEmpty($case['segments'], "{$case['id']} has no transcript");
            foreach ($case['expected'] as $exp) {
                $this->assertContains($exp['criterion_name'], $names, "{$case['id']}: unknown criterion \"{$exp['criterion_name']}\"");
            }
        }
    }

    public function test_eval_errors_when_golden_file_missing(): void
    {
        $this->artisan('qa:eval', ['--golden' => 'tests/golden/nonexistent.json'])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    public function test_eval_errors_when_template_not_in_db(): void
    {
        $this->artisan('qa:eval', ['--template' => 'Nonexistent Template'])
            ->assertFailed()
            ->expectsOutputToContain('Template not found');
    }

    public function test_rule_based_scorer_scores_every_golden_criterion(): void
    {
        $this->seedRealTemplate();
        $this->app->bind(ScoringService::class, KeywordScoringService::class);

        $this->artisan('qa:eval')
            ->doesntExpectOutputToContain('Criterion not found')
            ->expectsOutputToContain('Overall accuracy');
    }

    public function test_eval_leaves_no_temp_records(): void
    {
        $this->seedRealTemplate();
        $this->app->bind(ScoringService::class, FakeScoringService::class);

        $before = Call::count();
        $this->artisan('qa:eval');

        $this->assertSame($before, Call::count(), 'qa:eval must clean up temp call records');
        $this->assertFalse(Agent::where('name', '_eval')->exists(), 'qa:eval must remove its temporary agent');
    }

    public function test_eval_exit_code_reflects_accuracy(): void
    {
        $this->seedRealTemplate();
        $this->app->bind(ScoringService::class, FakeScoringService::class);

        // The fake scorer passes everything; gs_001 is the fully compliant call, so it scores 100%.
        $golden = $this->goldenSet();
        $golden['cases'] = [$golden['cases'][0]];
        $tmpPath = sys_get_temp_dir() . '/gs_test.json';
        file_put_contents($tmpPath, json_encode($golden));

        $this->artisan('qa:eval', ['--golden' => $tmpPath])->assertSuccessful();

        unlink($tmpPath);
    }

    public function test_eval_prints_false_pass_false_fail_breakdown(): void
    {
        $this->seedRealTemplate();
        // Passing everything produces false passes on the calls labelled as failures.
        $this->app->bind(ScoringService::class, FakeScoringService::class);

        $this->artisan('qa:eval')
            ->assertFailed()
            ->expectsOutputToContain('Per-criterion error breakdown')
            ->expectsOutputToContain('Overall false-pass');
    }

    public function test_eval_fails_cleanly_when_no_template_exists(): void
    {
        User::factory()->create(['role' => 'manager']);

        $before = Call::count();
        $this->artisan('qa:eval')->assertFailed();
        $this->assertSame($before, Call::count());
    }
}
