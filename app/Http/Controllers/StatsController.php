<?php

namespace App\Http\Controllers;

use App\Models\Call;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Call::class);

        return view('stats.index', $this->buildStats());
    }

    /** All chart data, scoped to the authenticated user's role. */
    public static function buildStats(): array
    {
        $isAdmin = auth()->user()->isAdmin();

        $base = Call::query()
            ->where('status', 'done')
            ->when(! $isAdmin, fn ($q) => $q->where('uploaded_by', auth()->id()));

        return [
            'outcomesData'      => static::outcomes($base),
            'agentsData'        => static::callsPerAgent($base),
            'agentScoresData'   => static::avgScorePerAgent($base),
            'failedCriteriaData'=> static::mostFailedCriteria($base),
            'trendData'         => static::scoreTrend($base),
            'totalDone'         => (clone $base)->count(),
        ];
    }

    // ---------------------------------------------------------

    private static function outcomes($base): array
    {
        $rows = (clone $base)
            ->select('outcome', DB::raw('count(*) as count'))
            ->whereNotNull('outcome')
            ->groupBy('outcome')
            ->pluck('count', 'outcome');

        return [
            'pass'      => (int) ($rows['pass']      ?? 0),
            'fail'      => (int) ($rows['fail']      ?? 0),
            'auto_fail' => (int) ($rows['auto_fail'] ?? 0),
        ];
    }

    private static function callsPerAgent($base): array
    {
        return (clone $base)
            ->join('agents', 'agents.id', '=', 'calls.agent_id')
            ->select('agents.name', DB::raw('count(*) as count'))
            ->groupBy('agents.id', 'agents.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])
            ->all();
    }

    private static function avgScorePerAgent($base): array
    {
        return (clone $base)
            ->join('agents', 'agents.id', '=', 'calls.agent_id')
            ->select('agents.name', DB::raw('avg(score_percent) as avg'))
            ->whereNotNull('score_percent')
            ->groupBy('agents.id', 'agents.name')
            ->orderBy('agents.name')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'avg' => round((float) $r->avg, 2)])
            ->all();
    }

    private static function mostFailedCriteria($base): array
    {
        $callIds = (clone $base)->pluck('calls.id');

        if ($callIds->isEmpty()) {
            return [];
        }

        return DB::table('criterion_results')
            ->whereIn('call_id', $callIds)
            ->whereIn('ai_result', ['fail', 'no'])
            ->select('criterion_name as name', DB::raw('count(*) as count'))
            ->groupBy('criterion_name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])
            ->all();
    }

    private static function scoreTrend($base): array
    {
        $rows = (clone $base)
            ->select(
                DB::raw('DATE(calls.created_at) as date'),
                DB::raw('avg(score_percent) as avg'),
                DB::raw('count(*) as count'),
            )
            ->whereNotNull('score_percent')
            ->where('calls.created_at', '>=', now()->subDays(21)->startOfDay())
            ->groupBy(DB::raw('DATE(calls.created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in every day so the line is continuous (null for days with no data)
        $result = [];
        for ($i = 20; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row  = $rows->get($date);
            $result[] = [
                'date'  => now()->subDays($i)->format('d M'),
                'avg'   => $row ? round((float) $row->avg, 2) : null,
                'count' => $row ? (int) $row->count : 0,
            ];
        }

        return $result;
    }
}
