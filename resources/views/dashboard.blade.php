<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-base font-semibold text-slate-800">Dashboard</h1>
            <p class="text-xs text-slate-400 hidden sm:block">Today's QA overview</p>
        </div>
        <a href="{{ route('calls.create') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">Upload Call</span>
            <span class="sm:hidden">Upload</span>
        </a>
    </x-slot>

    <div class="p-4 sm:p-6 space-y-6 max-w-7xl mx-auto w-full">

        {{-- Stat tiles --}}
        @php
            $passRate = null;
            // We compute pass rate from needs review context if available
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Processed Today</p>
                    <div class="w-8 h-8 bg-indigo-50 rounded-xl flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900">{{ $todayDone }}</p>
                <p class="text-xs text-slate-400 mt-1">calls scored</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Avg Score</p>
                    <div class="w-8 h-8 bg-emerald-50 rounded-xl flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                </div>
                @if ($todayAvgScore !== null)
                    <p class="text-3xl font-bold {{ $todayAvgScore >= 80 ? 'text-emerald-600' : ($todayAvgScore >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                        {{ number_format($todayAvgScore, 1) }}%
                    </p>
                @else
                    <p class="text-3xl font-bold text-slate-300">—</p>
                @endif
                <p class="text-xs text-slate-400 mt-1">today's average</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Needs Review</p>
                    <div class="w-8 h-8 {{ $needsReviewCount > 0 ? 'bg-amber-50' : 'bg-slate-50' }} rounded-xl flex items-center justify-center">
                        <svg class="w-4 h-4 {{ $needsReviewCount > 0 ? 'text-amber-500' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold {{ $needsReviewCount > 0 ? 'text-amber-600' : 'text-slate-900' }}">
                    {{ $needsReviewCount }}
                </p>
                <p class="text-xs text-slate-400 mt-1">flagged for review</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pass Rate</p>
                    <div class="w-8 h-8 bg-indigo-50 rounded-xl flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                @php
                    $todayTotal = \App\Models\Call::where('status','done')
                        ->whereDate('updated_at', today())
                        ->count();
                    $todayPassed = \App\Models\Call::where('status','done')
                        ->where('outcome','pass')
                        ->whereDate('updated_at', today())
                        ->count();
                    $rate = $todayTotal > 0 ? round($todayPassed / $todayTotal * 100) : null;
                @endphp
                @if ($rate !== null)
                    <p class="text-3xl font-bold {{ $rate >= 80 ? 'text-emerald-600' : ($rate >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                        {{ $rate }}%
                    </p>
                @else
                    <p class="text-3xl font-bold text-slate-300">—</p>
                @endif
                <p class="text-xs text-slate-400 mt-1">today's pass rate</p>
            </div>
        </div>

        {{-- Charts: outcomes donut + score trend --}}
        @php
            $hasOutcomes = ($outcomesData['pass'] + $outcomesData['fail'] + $outcomesData['auto_fail']) > 0;
            $hasTrend    = collect($trendData)->contains(fn($d) => $d['avg'] !== null);
        @endphp
        @if ($hasOutcomes || $hasTrend)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Outcomes donut --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-0.5">Call Outcomes</h3>
                <p class="text-xs text-slate-400 mb-4">All-time pass · fail · auto-fail</p>
                @if ($hasOutcomes)
                    <div class="relative h-44">
                        <canvas id="dash-outcomes"></canvas>
                    </div>
                @else
                    <div class="h-44 flex items-center justify-center text-sm text-slate-400">No scored calls yet.</div>
                @endif
            </div>

            {{-- Score trend --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-0.5">Score Trend</h3>
                <p class="text-xs text-slate-400 mb-4">Average QA score — last 3 weeks</p>
                @if ($hasTrend)
                    <div class="relative h-44">
                        <canvas id="dash-trend"></canvas>
                    </div>
                @else
                    <div class="h-44 flex items-center justify-center text-sm text-slate-400">No data in this period.</div>
                @endif
            </div>

        </div>
        @endif

        {{-- Main content: needs-review queue --}}
        @if ($needsReviewCalls->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></div>
                        <h3 class="text-sm font-semibold text-slate-800">Needs Human Review</h3>
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-full">
                            {{ $needsReviewCalls->count() }}
                        </span>
                    </div>
                    <a href="{{ route('calls.index', ['status' => 'done']) }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View all calls →</a>
                </div>

                <div class="divide-y divide-slate-50">
                    @foreach ($needsReviewCalls as $call)
                        <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50 transition-colors">
                            <div class="w-9 h-9 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 text-xs font-bold shrink-0">
                                {{ strtoupper(substr($call->agent->name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ $call->agent->name }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $call->template->name }}</p>
                            </div>
                            @if ($call->score_percent !== null)
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-bold {{ $call->score_percent >= 80 ? 'text-emerald-600' : ($call->score_percent >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                        {{ number_format($call->score_percent, 0) }}%
                                    </p>
                                </div>
                            @endif
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-full shrink-0">
                                {{ $call->needs_review_count }} {{ Str::plural('flag', $call->needs_review_count) }}
                            </span>
                            <a href="{{ route('calls.show', $call) }}"
                               class="shrink-0 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                Review
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-10 text-center">
                <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-slate-800 mb-1">All clear</h3>
                <p class="text-sm text-slate-400 mb-4">No calls need review right now.</p>
                <div class="flex items-center justify-center gap-3">
                    <a href="{{ route('calls.index') }}"
                       class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded-xl hover:bg-slate-200 transition-colors">
                        View all calls
                    </a>
                    <a href="{{ route('calls.create') }}"
                       class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-xl hover:bg-indigo-700 transition-colors">
                        Upload a call
                    </a>
                </div>
            </div>
        @endif

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var C = window.SalesQaCharts;
        if (!C) return;
        @if ($hasOutcomes)
        C.outcomesDonut(document.getElementById('dash-outcomes'), @json($outcomesData));
        @endif
        @if ($hasTrend)
        C.scoreTrendLine(document.getElementById('dash-trend'), @json($trendData));
        @endif
    });
    </script>
</x-app-layout>
