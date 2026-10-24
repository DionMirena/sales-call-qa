<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use Illuminate\Console\Command;

class QaStatsCommand extends Command
{
    protected $signature = 'qa:stats
        {--days=30 : Limit stats to the last N days (0 = all time)}
        {--template= : Filter by template name}';

    protected $description = 'Show aggregate QA statistics from the database';

    public function handle(): int
    {
        $days  = (int) $this->option('days');
        $since = $days > 0 ? now()->subDays($days) : null;

        $templateFilter = null;
        if ($tplName = $this->option('template')) {
            $templateFilter = Template::where('name', $tplName)->first();
            if (! $templateFilter) {
                $this->error("Template not found: \"{$tplName}\"");
                return self::FAILURE;
            }
        }

        $heading = $days > 0 ? "Last {$days} days" : 'All time';
        if ($templateFilter) {
            $heading .= " · Template: {$templateFilter->name}";
        }
        $this->info("QA Stats — {$heading}");
        $this->newLine();

        // ── Overall stats ────────────────────────────────────────────────────────
        $baseQuery = Call::where('status', 'done')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->when($templateFilter, fn ($q) => $q->where('template_id', $templateFilter->id));

        $total        = (clone $baseQuery)->count();
        $avgScore     = (clone $baseQuery)->whereNotNull('score_percent')->avg('score_percent');
        $passCount    = (clone $baseQuery)->where('outcome', 'pass')->count();
        $failCount    = (clone $baseQuery)->where('outcome', 'fail')->count();
        $autoFail     = (clone $baseQuery)->where('outcome', 'auto_fail')->count();
        $passRate     = $total ? round($passCount / $total * 100) : null;
        $needsReview  = Call::whereHas('criterionResults', fn ($q) => $q->where('needs_review', true))->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total calls scored',         $total],
                ['Average score',              $avgScore !== null ? round($avgScore, 1) . '%' : '—'],
                ['Pass',                       "{$passCount}" . ($total ? "  (" . round($passCount / $total * 100) . "%)" : '')],
                ['Fail',                       "{$failCount}" . ($total ? "  (" . round($failCount / $total * 100) . "%)" : '')],
                ['Auto-fail',                  "{$autoFail}" . ($total ? "  (" . round($autoFail / $total * 100) . "%)" : '')],
                ['Pass rate',                  $passRate !== null ? "{$passRate}%" : '—'],
                ['Calls needing review (all)', $needsReview],
            ]
        );

        if ($total === 0) {
            $this->warn('No completed calls found for this period.');
            return self::SUCCESS;
        }

        // ── Per-template breakdown ────────────────────────────────────────────────
        $this->newLine();
        $this->line('<options=bold>Per-template breakdown:</>');

        $templates = Template::whereHas('calls', fn ($q) => $q
            ->where('status', 'done')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
        )->get();

        $tplRows = [];
        foreach ($templates as $tpl) {
            $tplBase  = Call::where('template_id', $tpl->id)->where('status', 'done')
                ->when($since, fn ($q) => $q->where('updated_at', '>=', $since));
            $cnt      = (clone $tplBase)->count();
            $avg      = (clone $tplBase)->whereNotNull('score_percent')->avg('score_percent');
            $passes   = (clone $tplBase)->where('outcome', 'pass')->count();
            $rate     = $cnt ? round($passes / $cnt * 100) : 0;
            $tplRows[] = [$tpl->name, $cnt, $avg !== null ? round($avg, 1) . '%' : '—', "{$rate}%"];
        }
        $this->table(['Template', 'Calls', 'Avg Score', 'Pass Rate'], $tplRows);

        // ── Top failed criteria (by AI result — fast DB query) ────────────────────
        $this->newLine();
        $this->line('<options=bold>Top 10 most-failed criteria (AI result, not override-adjusted):</>');

        $scopedCallIds = Call::select('id')
            ->where('status', 'done')
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->when($templateFilter, fn ($q) => $q->where('template_id', $templateFilter->id));

        $failQuery = CriterionResult::query()
            ->whereIn('call_id', $scopedCallIds)
            ->whereIn('ai_result', ['fail', 'no'])
            ->selectRaw('criterion_name, COUNT(*) as fail_count')
            ->groupBy('criterion_name')
            ->orderByDesc('fail_count')
            ->limit(10)
            ->get();

        if ($failQuery->isEmpty()) {
            $this->line('  No failures recorded.');
        } else {
            $this->table(
                ['Criterion', 'Fail count'],
                $failQuery->map(fn ($r) => [$r->criterion_name, $r->fail_count])->toArray()
            );
        }

        // ── Override rate per criterion ───────────────────────────────────────────
        // "Changed" = a manager overrode the result to a different value, i.e. disagreed
        // with the scorer. "Mark AI correct" keeps the same value, so it only counts as reviewed.
        $this->newLine();
        $this->line('<options=bold>Override rate per criterion (high change rate = reword the criterion):</>');

        $overrideRows = CriterionResult::query()
            ->whereIn('call_id', $scopedCallIds)
            ->where('criterion_type', '!=', 'extract')
            ->selectRaw('criterion_name, COUNT(*) as scored')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM overrides o WHERE o.criterion_result_id = criterion_results.id) THEN 1 ELSE 0 END) as reviewed')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM overrides o WHERE o.criterion_result_id = criterion_results.id AND o.new_result <> o.old_result) THEN 1 ELSE 0 END) as changed')
            ->groupBy('criterion_name')
            ->get()
            ->sortByDesc(fn ($r) => $r->changed / $r->scored)
            ->values();

        $this->table(
            ['Criterion', 'Scored', 'Reviewed', 'Changed', 'Change rate'],
            $overrideRows->map(fn ($r) => [
                $r->criterion_name,
                $r->scored,
                (int) $r->reviewed,
                (int) $r->changed,
                round($r->changed / $r->scored * 100) . '%',
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
