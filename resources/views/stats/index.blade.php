<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-base font-semibold text-slate-800">Statistics</h1>
            <p class="text-xs text-slate-400 hidden sm:block">
                {{ $totalDone }} completed {{ Str::plural('call', $totalDone) }}
                @if(!auth()->user()->isAdmin()) · your calls only @endif
            </p>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 max-w-7xl mx-auto w-full space-y-5">

        @if($totalDone === 0)
            {{-- Empty state --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm py-20 flex flex-col items-center gap-3">
                <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center">
                    <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-slate-700">No data yet</p>
                <p class="text-sm text-slate-400">Process some calls to see statistics here.</p>
                <a href="{{ route('calls.create') }}"
                   class="mt-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                    Upload a call
                </a>
            </div>
        @else

        {{-- Row 1: Outcomes + Calls per agent --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            {{-- Outcomes donut --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-1">Call Outcomes</h3>
                <p class="text-xs text-slate-400 mb-4">Pass · Fail · Auto-fail breakdown</p>
                @if($outcomesData['pass'] + $outcomesData['fail'] + $outcomesData['auto_fail'] === 0)
                    <div class="h-52 flex items-center justify-center text-sm text-slate-400">No scored calls yet.</div>
                @else
                    <div class="relative h-52">
                        <canvas id="chart-outcomes"></canvas>
                    </div>
                @endif
            </div>

            {{-- Calls per agent donut --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-1">Calls per Agent</h3>
                <p class="text-xs text-slate-400 mb-4">Distribution of completed calls</p>
                @if(empty($agentsData))
                    <div class="h-52 flex items-center justify-center text-sm text-slate-400">No data yet.</div>
                @else
                    <div class="relative h-52">
                        <canvas id="chart-agents"></canvas>
                    </div>
                @endif
            </div>

        </div>

        {{-- Row 2: Avg score per agent + Most failed criteria --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            {{-- Avg score per agent bar --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-1">Average Score per Agent</h3>
                <p class="text-xs text-slate-400 mb-4">Green ≥80% · Amber ≥60% · Red &lt;60%</p>
                @if(empty($agentScoresData))
                    <div class="h-52 flex items-center justify-center text-sm text-slate-400">No data yet.</div>
                @else
                    <div class="relative h-52">
                        <canvas id="chart-agent-scores"></canvas>
                    </div>
                @endif
            </div>

            {{-- Most failed criteria bar (horizontal) --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-1">Most Failed Criteria</h3>
                <p class="text-xs text-slate-400 mb-4">Top 8 criteria by failure count</p>
                @if(empty($failedCriteriaData))
                    <div class="h-52 flex items-center justify-center text-sm text-slate-400">No failures recorded yet.</div>
                @else
                    @php $chartH = max(180, count($failedCriteriaData) * 30); @endphp
                    <div class="relative" style="height: {{ $chartH }}px">
                        <canvas id="chart-failed-criteria"></canvas>
                    </div>
                @endif
            </div>

        </div>

        {{-- Row 3: Score trend (full width) --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Score Trend — Last 3 Weeks</h3>
            <p class="text-xs text-slate-400 mb-4">Average QA score per day</p>
            @php $hasTrend = collect($trendData)->filter(fn($d) => $d['avg'] !== null)->isNotEmpty(); @endphp
            @if(!$hasTrend)
                <div class="h-48 flex items-center justify-center text-sm text-slate-400">No data in this period.</div>
            @else
                <div class="relative h-48">
                    <canvas id="chart-trend"></canvas>
                </div>
            @endif
        </div>

        @endif
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var C = window.SalesQaCharts;
        if (!C) return;

        @if(!empty($outcomesData) && ($outcomesData['pass'] + $outcomesData['fail'] + $outcomesData['auto_fail'] > 0))
        C.outcomesDonut(document.getElementById('chart-outcomes'), @json($outcomesData));
        @endif

        @if(!empty($agentsData))
        C.agentsDonut(document.getElementById('chart-agents'), @json($agentsData));
        @endif

        @if(!empty($agentScoresData))
        C.agentScoreBar(document.getElementById('chart-agent-scores'), @json($agentScoresData));
        @endif

        @if(!empty($failedCriteriaData))
        C.failedCriteriaBar(document.getElementById('chart-failed-criteria'), @json($failedCriteriaData));
        @endif

        @if(isset($hasTrend) && $hasTrend)
        C.scoreTrendLine(document.getElementById('chart-trend'), @json($trendData));
        @endif
    });
    </script>
</x-app-layout>
