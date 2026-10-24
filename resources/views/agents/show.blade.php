<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 min-w-0">
            <a href="{{ route('agents.index') }}"
               class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-xs font-bold text-indigo-700 shrink-0">
                    {{ strtoupper(substr($agent->name, 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-slate-800 truncate">{{ $agent->name }}</h1>
                    @if ($agent->team)
                        <p class="text-xs text-slate-400">{{ $agent->team }}</p>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 space-y-5 max-w-7xl mx-auto w-full">

        {{-- Stat tiles --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Completed Calls</p>
                <p class="text-3xl font-bold text-slate-900">{{ $totalDone }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Average Score</p>
                @if ($avgScore !== null)
                    <p class="text-3xl font-bold {{ $avgScore >= 80 ? 'text-emerald-600' : ($avgScore >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                        {{ $avgScore }}%
                    </p>
                @else
                    <p class="text-3xl font-bold text-slate-300">—</p>
                @endif
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Pass Rate</p>
                @if ($passRate !== null)
                    <p class="text-3xl font-bold {{ $passRate >= 80 ? 'text-emerald-600' : ($passRate >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                        {{ $passRate }}%
                    </p>
                @else
                    <p class="text-3xl font-bold text-slate-300">—</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {{-- Top failed criteria --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-4">Top Failed Criteria</h3>
                @if (empty($failCounts))
                    <div class="py-8 text-center">
                        <div class="w-8 h-8 bg-emerald-50 rounded-xl flex items-center justify-center mx-auto mb-2">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-slate-400">No failures recorded yet.</p>
                    </div>
                @else
                    @php $maxFails = max(array_values($failCounts)); @endphp
                    <ul class="space-y-3">
                        @foreach ($failCounts as $name => $count)
                            <li>
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-sm text-slate-700 truncate mr-3">{{ $name }}</span>
                                    <span class="text-xs font-semibold text-red-600 shrink-0">{{ $count }} {{ Str::plural('fail', $count) }}</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-1.5">
                                    <div class="bg-red-400 h-1.5 rounded-full transition-all"
                                         style="width: {{ round($count / $maxFails * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Score trend --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-4">Score Trend — Last 8 Weeks</h3>
                @if (collect($weeks)->filter(fn ($w) => $w['avg'] !== null)->isEmpty())
                    <div class="py-8 text-center">
                        <p class="text-sm text-slate-400">No data for this period.</p>
                    </div>
                @else
                    <div class="flex items-end gap-1.5 h-28">
                        @foreach ($weeks as $week)
                            <div class="flex-1 flex flex-col items-center gap-1 group">
                                @if ($week['avg'] !== null)
                                    @php $h = max(4, round($week['avg'])); @endphp
                                    <div class="w-full rounded-t-lg transition-all duration-300"
                                         style="height: {{ $h }}%;
                                                background-color: {{ $week['avg'] >= 80 ? '#34d399' : ($week['avg'] >= 60 ? '#fbbf24' : '#f87171') }};
                                                min-height: 4px; max-height: 100%;"
                                         title="{{ $week['label'] }}: {{ $week['avg'] }}% ({{ $week['count'] }} {{ Str::plural('call', $week['count']) }})">
                                    </div>
                                @else
                                    <div class="w-full rounded-t-lg bg-slate-100" style="height: 4px; min-height: 4px;"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="flex gap-1.5 mt-2">
                        @foreach ($weeks as $week)
                            <div class="flex-1 text-center text-xs text-slate-300 truncate">
                                {{ Str::after($week['label'], ' ') }}
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-3 mt-2 text-xs text-slate-400">
                        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-emerald-400 rounded-full inline-block"></span> ≥80%</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-amber-400 rounded-full inline-block"></span> ≥60%</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-400 rounded-full inline-block"></span> &lt;60%</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent calls --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700">Recent Calls</h3>
            </div>
            @if ($recentCalls->isEmpty())
                <div class="py-10 text-center text-sm text-slate-400">No completed calls yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-50 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Call</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Template</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Score</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Outcome</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($recentCalls as $call)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <a href="{{ route('calls.show', $call) }}" class="font-bold text-indigo-600 hover:text-indigo-800">#{{ $call->id }}</a>
                                        <span class="text-xs text-slate-400 ml-1">{{ $call->original_filename }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 truncate max-w-[200px]">{{ $call->template->name }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($call->score_percent !== null)
                                            <span class="font-bold {{ $call->score_percent >= 80 ? 'text-emerald-600' : ($call->score_percent >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                                {{ number_format($call->score_percent, 1) }}%
                                            </span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($call->outcome === 'pass')
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Pass</span>
                                        @elseif ($call->outcome === 'fail')
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Fail</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-200 text-red-800">Auto-fail</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-400 text-xs">{{ $call->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="{{ route('calls.show', $call) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
