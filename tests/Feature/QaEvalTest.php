<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Template;
use App\Models\User;
use App\Services\FakeScoringService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class QaEvalTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(User $manager): Template
    {
        $tpl = Template::factory()->create([
            'created_by'     => $manager->id,
            'name'           => 'Standard Insurance QA',
            'pass_threshold' => 80,
        ]);

        $criteria = [
            ['name' => 'Recording Disclosure',    'type' => 'pass_fail', 'weight' => 1, 'is_critical' => true],
            ['name' => 'Greeting and Introduction','type' => 'pass_fail', 'weight' => 1, 'is_critical' => false],
            ['name' => 'Discovery Questions',      'type' => 'pass_fail', 'weight' => 1, 'is_critical' => false],
            ['name' => 'Premium Quote Accuracy',   'type' => 'pass_fail', 'weight' => 1, 'is_critical' => false],
            ['name' => 'Cooling-Off Period',        'type' => 'pass_fail', 'weight' => 1, 'is_critical' => false],
            ['name' => 'Clear Close',              'type' => 'pass_fail', 'weight' => 1, 'is_critical' => false],
        ];

        foreach ($criteria as $i => $attrs) {
            $tpl->criteria()->create(array_merge([
                'position'        => $i + 1,
                'description'     => $attrs['name'],
                'fail_conditions' => null,
                'phase_hint'      => 'anywhere',
            ], $attrs));
        }

        return $tpl;
    }

    public function test_eval_errors_when_golden_file_missing(): void
    {
        $this->artisan('qa:eval', ['--golden' => 'tests/golden/nonexistent.json'])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    public function test_eval_errors_when_template_not_in_db(): void
    {
        Config::set('qa.fake_ai', true);

        $this->artisan('qa:eval', ['--template' => 'Nonexistent Template'])
            ->assertFailed()
            ->expectsOutputToContain('Template not found');
    }

    public function test_eval_runs_and_produces_accuracy_output(): void
    {
        Config::set('qa.fake_ai', true);

        $manager = User::factory()->create(['role' => 'manager']);
        $this->makeTemplate($manager);

        // The full golden set has gs_002 (all-fail expected) which the fake AI
        // scores wrong — exit code may be 0 or 1 depending on overall accuracy.
        // We only verify the command completes and shows the summary line.
        $this->artisan('qa:eval')
            ->expectsOutputToContain('Overall accuracy');
    }

    public function test_eval_leaves_no_temp_records(): void
    {
        Config::set('qa.fake_ai', true);

        $manager = User::factory()->create(['role' => 'manager']);
        $this->makeTemplate($manager);

        $before = \App\Models\Call::count();
        $this->artisan('qa:eval');
        $after = \App\Models\Call::count();

        $this->assertEquals($before, $after, 'qa:eval must clean up temp call records');
    }

    public function test_eval_exit_code_reflects_accuracy(): void
    {
        Config::set('qa.fake_ai', true);
        $this->app->bind(ScoringService::class, FakeScoringService::class);

        $manager = User::factory()->create(['role' => 'manager']);
        $this->makeTemplate($manager);

        // With fake AI (all pass) vs gs_001 (all pass expected) → 100% → exit 0
        // With gs_002 (all fail expected) → 0% → but the file has BOTH cases
        // Net accuracy depends on mix; we just assert the command runs successfully
        // since gs_001 (6 criteria all pass) dominates the 12-item set at 50%
        // which is < 80% → exit 1. Use a custom golden set with only gs_001 to get 100%.

        $gs001Only = [
            'version'       => 1,
            'template_name' => 'Standard Insurance QA',
            'cases'         => [json_decode(file_get_contents(base_path('tests/golden/golden_set.json')), true)['cases'][0]],
        ];

        $tmpPath = sys_get_temp_dir() . '/gs_test.json';
        file_put_contents($tmpPath, json_encode($gs001Only));

        $this->artisan('qa:eval', ['--golden' => $tmpPath])
            ->assertSuccessful(); // all-pass expected + fake-AI all-pass = 100% ≥ 80%

        unlink($tmpPath);
    }

    public function test_eval_cleans_up_on_scoring_failure(): void
    {
        // Without a template, scoring will fail; command should still exit cleanly
        Config::set('qa.fake_ai', true);
        User::factory()->create(['role' => 'manager']);
        // No template created → command exits with failure but does not throw

        $before = \App\Models\Call::count();
        $this->artisan('qa:eval')->assertFailed();
        $this->assertEquals($before, \App\Models\Call::count());
    }
}
