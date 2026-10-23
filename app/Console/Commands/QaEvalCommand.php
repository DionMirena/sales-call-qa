<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Console\Command;
use Throwable;

class QaEvalCommand extends Command
{
    protected $signature = 'qa:eval
        {--golden=tests/golden/golden_set.json : Path to the golden set JSON file}
        {--template= : Override the template name from the JSON file}';

    protected $description = 'Evaluate AI scoring accuracy against the golden set';

    public function handle(ScoringService $scoring): int
    {
        // Accept both relative (resolved from project root) and absolute paths
        $raw  = $this->option('golden');
        $path = file_exists($raw) ? $raw : base_path($raw);

        if (! file_exists($path)) {
            $this->error("Golden set not found: {$path}");
            $this->line('Run with --golden=<path> to specify a different file.');
            return self::FAILURE;
        }

        $data  = json_decode(file_get_contents($path), true);
        $cases = $data['cases'] ?? [];

        if (empty($cases)) {
            $this->error('No cases found in golden set.');
            return self::FAILURE;
        }

        $templateName = $this->option('template') ?? $data['template_name'] ?? null;
        $template     = $templateName ? Template::where('name', $templateName)->first() : null;

        if (! $template) {
            $this->error("Template not found: \"{$templateName}\"");
            $this->line('Ensure the template exists in the database, or pass --template=<name>.');
            return self::FAILURE;
        }

        $fakeMode = config('qa.fake_ai');
        if ($fakeMode) {
            $this->warn('Running in FAKE AI mode (QA_FAKE_AI=true). Accuracy results are not meaningful.');
        }

        // Ensure an eval agent and user exist
        $agent = Agent::firstOrCreate(['name' => '_eval'], ['team' => null]);
        $user  = User::first();

        if (! $user) {
            $this->error('No users found in the database. Run database seeders first.');
            return self::FAILURE;
        }

        $allRows    = [];
        $totalRight = 0;
        $totalItems = 0;

        foreach ($cases as $case) {
            $this->line("<fg=cyan>Case {$case['id']}:</> {$case['description']}");

            // Create a temporary call record
            $call = Call::create([
                'agent_id'          => $agent->id,
                'template_id'       => $template->id,
                'uploaded_by'       => $user->id,
                'original_path'     => '_eval',
                'original_filename' => '_eval',
                'status'            => 'scoring',
                'agent_speaker'     => 0,
            ]);

            // Insert segments
            $bulk = [];
            foreach ($case['segments'] as $i => $seg) {
                $bulk[] = [
                    'call_id'  => $call->id,
                    'code'     => 'S' . ($i + 1),
                    'speaker'  => $seg['speaker'],
                    'start_ms' => $seg['start_ms'],
                    'end_ms'   => $seg['end_ms'],
                    'text'     => $seg['text'],
                ];
            }
            TranscriptSegment::insert($bulk);

            // Run scoring
            try {
                $scoring->score($call->fresh());
            } catch (Throwable $e) {
                $this->error("  Scoring failed: {$e->getMessage()}");
                $call->delete();
                continue;
            }

            // Compare results
            $results = $call->criterionResults()->get()->keyBy('criterion_name');
            $caseRight = 0;
            $caseTotal = 0;

            foreach ($case['expected'] as $exp) {
                $result = $results->get($exp['criterion_name']);

                if (! $result) {
                    $this->warn("  Criterion not found in results: \"{$exp['criterion_name']}\"");
                    $allRows[] = [$case['id'], $exp['criterion_name'], $exp['result'], '(not scored)', '✗'];
                    $totalItems++;
                    continue;
                }

                $caseTotal++;
                $totalItems++;
                $correct = $result->ai_result === $exp['result'];
                if ($correct) {
                    $caseRight++;
                    $totalRight++;
                }

                $allRows[] = [
                    $case['id'],
                    $exp['criterion_name'],
                    $exp['result'],
                    $result->ai_result,
                    $correct ? '<fg=green>✓</>' : '<fg=red>✗</>',
                ];
            }

            $pct = $caseTotal ? round($caseRight / $caseTotal * 100) : 0;
            $colour = $pct >= 80 ? 'green' : ($pct >= 60 ? 'yellow' : 'red');
            $this->line("  <fg={$colour}>{$caseRight}/{$caseTotal} correct ({$pct}%)</>");

            // Clean up temp records
            $call->delete();
        }

        // Summary table
        $this->newLine();
        $this->table(
            ['Case', 'Criterion', 'Expected', 'AI result', ''],
            $allRows
        );

        $overallPct = $totalItems ? round($totalRight / $totalItems * 100) : 0;
        $colour     = $overallPct >= 80 ? 'green' : ($overallPct >= 60 ? 'yellow' : 'red');
        $this->newLine();
        $this->line("<fg={$colour};options=bold>Overall accuracy: {$totalRight}/{$totalItems} ({$overallPct}%)</>");

        return $overallPct >= 80 ? self::SUCCESS : self::FAILURE;
    }
}
