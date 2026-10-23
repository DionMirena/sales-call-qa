<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Call;
use App\Models\CriterionResult;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(): View
    {
        $agents = Agent::withCount([
            'calls as total_calls_count',
            'calls as done_calls_count' => fn ($q) => $q->where('status', 'done'),
        ])
            ->orderBy('name')
            ->paginate(25);

        return view('agents.index', compact('agents'));
    }

    public function show(Agent $agent): View
    {
        $doneCalls = $agent->calls()
            ->where('status', 'done')
            ->whereNotNull('score_percent')
            ->orderBy('created_at')
            ->get(['id', 'score_percent', 'outcome', 'created_at']);

        $totalDone = $doneCalls->count();
        $avgScore  = $totalDone ? round($doneCalls->avg('score_percent'), 1) : null;
        $passCount = $doneCalls->where('outcome', 'pass')->count();
        $passRate  = $totalDone ? round($passCount / $totalDone * 100) : null;

        // Top 5 most-failed criteria, respecting overrides
        $failCounts = [];
        if ($totalDone > 0) {
            $results = CriterionResult::with('overrides')
                ->whereIn('call_id', $doneCalls->pluck('id'))
                ->get(['id', 'call_id', 'criterion_name', 'ai_result']);

            foreach ($results as $r) {
                if (in_array($r->effectiveResult(), ['fail', 'no'])) {
                    $failCounts[$r->criterion_name] = ($failCounts[$r->criterion_name] ?? 0) + 1;
                }
            }
            arsort($failCounts);
            $failCounts = array_slice($failCounts, 0, 5, true);
        }

        // Score trend: last 8 weeks (most recent on the right)
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $start     = now()->startOfWeek()->subWeeks($i);
            $end       = (clone $start)->endOfWeek();
            $weekCalls = $doneCalls->filter(fn ($c) => $c->created_at->between($start, $end));
            $weeks[]   = [
                'label' => $start->format('M d'),
                'avg'   => $weekCalls->count() ? round($weekCalls->avg('score_percent'), 1) : null,
                'count' => $weekCalls->count(),
            ];
        }

        return view('agents.show', compact('agent', 'totalDone', 'avgScore', 'passRate', 'failCounts', 'weeks'));
    }
}
