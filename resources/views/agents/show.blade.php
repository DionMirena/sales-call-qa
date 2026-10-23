<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $agent->name }}</h2>
                @if ($agent->team)
                    <p class="text-sm text-gray-500 mt-0.5">{{ $agent->team }}</p>
                @endif
            </div>
            <a href="{{ route('agents.index') }}"
               class="text-sm text-indigo-600 hover:text-indigo-800">&larr; All Agents</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Stats grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Completed Calls</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $totalDone }}</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Average Score</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">
                        @if ($avgScore !== null)
                            {{ $avgScore }}%
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Pass Rate</p>
                    <p class="mt-1 text-3xl font-bold {{ ($passRate ?? 0) >= 80 ? 'text-green-600' : 'text-red-600' }}">
                        @if ($passRate !== null)
                            {{ $passRate }}%
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Top failed criteria --}}
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Top Failed Criteria</h3>
                    @if (empty($failCounts))
                        <p class="text-sm text-gray-400">No failures recorded yet.</p>
                    @else
                        @php $maxFails = max(array_values($failCounts)); @endphp
                        <ul class="space-y-2">
                            @foreach ($failCounts as $name => $count)
                                <li>
                                    <div class="flex items-center justify-between mb-0.5">
                                        <span class="text-sm text-gray-700 truncate me-2">{{ $name }}</span>
                                        <span class="text-xs text-red-600 font-medium shrink-0">{{ $count }} {{ Str::plural('fail', $count) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                                        <div class="bg-red-400 h-1.5 rounded-full"
                                             style="width: {{ round($count / $maxFails * 100) }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Score trend --}}
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Score Trend (Last 8 Weeks)</h3>
                    @if (collect($weeks)->filter(fn ($w) => $w['avg'] !== null)->isEmpty())
                        <p class="text-sm text-gray-400">No data for this period.</p>
                    @else
                        <div class="flex items-end gap-1 h-24">
                            @foreach ($weeks as $week)
                                <div class="flex-1 flex flex-col items-center gap-0.5 group relative">
                                    @if ($week['avg'] !== null)
                                        @php $height = max(4, round($week['avg'])); @endphp
                                        <div class="w-full rounded-t"
                                             style="height: {{ $height }}%; background-color: {{ $week['avg'] >= 80 ? '#4ade80' : ($week['avg'] >= 60 ? '#fbbf24' : '#f87171') }}; min-height: 4px; max-height: 100%;"
                                             title="{{ $week['label'] }}: {{ $week['avg'] }}% ({{ $week['count'] }} {{ Str::plural('call', $week['count']) }})">
                                        </div>
                                    @else
                                        <div class="w-full rounded-t bg-gray-100" style="height: 4px; min-height: 4px;"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="flex gap-1 mt-1">
                            @foreach ($weeks as $week)
                                <div class="flex-1 text-center text-xs text-gray-400 truncate">{{ Str::after($week['label'], ' ') }}</div>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Green ≥80% · Yellow ≥60% · Red &lt;60%</p>
                    @endif
                </div>
            </div>

            {{-- Recent calls --}}
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Recent Calls</h3>
                </div>
                @php
                    $recentCalls = $agent->calls()->with('template')->where('status', 'done')->latest()->limit(10)->get();
                @endphp
                @if ($recentCalls->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-gray-400">No completed calls yet.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Template</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Score</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Outcome</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Date</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($recentCalls as $call)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-gray-700">{{ $call->template->name }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $call->score_percent !== null ? number_format($call->score_percent, 1).'%' : '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($call->outcome === 'pass')
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Pass</span>
                                        @elseif ($call->outcome === 'fail')
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Fail</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Auto-fail</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-400 text-xs">{{ $call->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('calls.show', $call) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
