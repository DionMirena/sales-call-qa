<?php

namespace App\Http\Controllers;

use App\Models\Call;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $isAdmin = auth()->user()->isAdmin();

        $todayDone = Call::when(! $isAdmin, fn ($q) => $q->where('uploaded_by', auth()->id()))
            ->where('status', 'done')
            ->whereDate('updated_at', today())
            ->count();

        $todayAvgScore = Call::when(! $isAdmin, fn ($q) => $q->where('uploaded_by', auth()->id()))
            ->where('status', 'done')
            ->whereDate('updated_at', today())
            ->whereNotNull('score_percent')
            ->avg('score_percent');

        $needsReviewCount = Call::when(! $isAdmin, fn ($q) => $q->where('uploaded_by', auth()->id()))
            ->whereHas('criterionResults', fn ($q) => $q->where('needs_review', true))
            ->count();

        $autoFailCount = Call::when(! $isAdmin, fn ($q) => $q->where('uploaded_by', auth()->id()))
            ->where('status', 'done')
            ->where('outcome', 'auto_fail')
            ->count();

        $needsReviewCalls = Call::with(['agent', 'template'])
            ->when(! $isAdmin, fn ($q) => $q->where('uploaded_by', auth()->id()))
            ->whereHas('criterionResults', fn ($q) => $q->where('needs_review', true))
            ->withCount(['criterionResults as needs_review_count' => fn ($q) => $q->where('needs_review', true)])
            ->oldest()
            ->limit(10)
            ->get();

        $stats       = StatsController::buildStats();
        $outcomesData = $stats['outcomesData'];
        $trendData    = $stats['trendData'];

        return view('dashboard', compact('todayDone', 'todayAvgScore', 'needsReviewCount', 'autoFailCount', 'needsReviewCalls', 'outcomesData', 'trendData'));
    }
}
