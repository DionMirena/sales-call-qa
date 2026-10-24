<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-base font-semibold text-slate-800">Calls</h1>
            <p class="text-xs text-slate-400 hidden sm:block">{{ $calls->total() }} {{ Str::plural('call', $calls->total()) }} found</p>
        </div>
        <a href="{{ route('calls.create') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">Upload Call</span>
        </a>
    </x-slot>

    <div class="p-4 sm:p-6 space-y-4 max-w-7xl mx-auto w-full">

        @if (session('success'))
            <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        {{-- Filters --}}
        @php
            $allFilterKeys  = ['agent_id','template_id','outcome','status','date_from','date_to','score_min','score_max'];
            $activeFilters  = collect(request()->only($allFilterKeys))->filter()->count()
                            + (request()->boolean('needs_review') ? 1 : 0);
        @endphp
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4"
             x-data="{ showFilters: {{ $activeFilters > 0 ? 'true' : 'false' }} }">
            <div class="flex items-center justify-between mb-3">
                <button @click="showFilters = !showFilters"
                        class="flex items-center gap-2 text-sm font-medium text-slate-700 hover:text-slate-900 transition-colors">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filters
                    @if($activeFilters)
                        <span class="px-1.5 py-0.5 bg-indigo-600 text-white text-xs rounded-full">{{ $activeFilters }}</span>
                    @endif
                </button>
                <div class="flex items-center gap-3">
                    @if($activeFilters)
                        <a href="{{ route('calls.index', request()->only('sort')) }}"
                           class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Clear all</a>
                    @endif
                    {{-- Sort: keeps the current filters by re-sending them as hidden fields --}}
                    <form method="GET" action="{{ route('calls.index') }}" class="flex items-center gap-2">
                        @foreach (request()->except(['sort', 'page']) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <label for="sort" class="text-xs text-slate-500">Sort</label>
                        <select id="sort" name="sort" onchange="this.form.submit()"
                                class="h-8 pl-2.5 pr-8 py-0 rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ([
                                'newest'      => 'Newest first',
                                'oldest'      => 'Oldest first',
                                'number_asc'  => 'Call # (1 → last)',
                                'number_desc' => 'Call # (last → 1)',
                                'longest'     => 'Longest first',
                                'shortest'    => 'Shortest first',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>

            <div x-show="showFilters">
                <form method="GET" action="{{ route('calls.index') }}">
                    @if (request()->filled('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Agent</label>
                            <select name="agent_id"
                                    class="w-full text-sm border-slate-200 rounded-xl bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">All agents</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Template</label>
                            <select name="template_id"
                                    class="w-full text-sm border-slate-200 rounded-xl bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">All templates</option>
                                @foreach ($templates as $tpl)
                                    <option value="{{ $tpl->id }}" {{ request('template_id') == $tpl->id ? 'selected' : '' }}>
                                        {{ $tpl->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Outcome</label>
                            <select name="outcome"
                                    class="w-full text-sm border-slate-200 rounded-xl bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">All outcomes</option>
                                <option value="pass" {{ request('outcome') === 'pass' ? 'selected' : '' }}>Pass</option>
                                <option value="fail" {{ request('outcome') === 'fail' ? 'selected' : '' }}>Fail</option>
                                <option value="auto_fail" {{ request('outcome') === 'auto_fail' ? 'selected' : '' }}>Auto-fail</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                            <select name="status"
                                    class="w-full text-sm border-slate-200 rounded-xl bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">All statuses</option>
                                <option value="done" {{ request('status') === 'done' ? 'selected' : '' }}>Done</option>
                                <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>Queued</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}"
                                   class="w-full text-sm border-slate-200 rounded-xl bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}"
                                   class="w-full text-sm border-slate-200 rounded-xl bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Score min %</label>
                            <input type="number" name="score_min" min="0" max="100" step="1"
                                   value="{{ request('score_min') }}" placeholder="0"
                                   class="w-full text-sm border-slate-200 rounded-xl bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Score max %</label>
                            <input type="number" name="score_max" min="0" max="100" step="1"
                                   value="{{ request('score_max') }}" placeholder="100"
                                   class="w-full text-sm border-slate-200 rounded-xl bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-4">
                        <label class="flex items-center gap-1.5 text-sm text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" name="needs_review" value="1"
                                   {{ request()->boolean('needs_review') ? 'checked' : '' }}
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Needs review only
                        </label>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                            Apply
                        </button>
                        <a href="{{ route('calls.index') }}"
                           class="px-4 py-2 bg-slate-100 text-slate-600 text-sm rounded-xl hover:bg-slate-200 transition-colors">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            @if ($calls->isEmpty())
                <div class="py-16 text-center">
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700 mb-1">No calls found</p>
                    <p class="text-sm text-slate-400 mb-4">
                        @if($activeFilters) Try adjusting your filters. @else Upload the first call to get started. @endif
                    </p>
                    <a href="{{ route('calls.create') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm rounded-xl hover:bg-indigo-700 transition-colors">
                        Upload a call
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Call</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Agent</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Template</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Length</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Score</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Outcome</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($calls as $call)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('calls.show', $call) }}" class="font-bold text-indigo-600 hover:text-indigo-800">#{{ $call->id }}</a>
                                        <p class="text-xs text-slate-400 max-w-[160px] truncate" title="{{ $call->original_filename }}">{{ $call->original_filename }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center text-xs font-bold text-indigo-700 shrink-0">
                                                {{ strtoupper(substr($call->agent->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <a href="{{ route('agents.show', $call->agent) }}"
                                                   class="font-semibold text-slate-800 hover:text-indigo-600 transition-colors">
                                                    {{ $call->agent->name }}
                                                </a>
                                                @if ($call->agent->team)
                                                    <p class="text-xs text-slate-400">{{ $call->agent->team }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 max-w-[160px] truncate">{{ $call->template->name }}</td>
                                    <td class="px-4 py-3 text-slate-600 tabular-nums whitespace-nowrap">
                                        {{-- minutes:seconds; minutes can go past 59 for long calls --}}
                                        {{ $call->duration_seconds !== null ? intdiv($call->duration_seconds, 60) . ':' . str_pad($call->duration_seconds % 60, 2, '0', STR_PAD_LEFT) : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($call->score_percent !== null)
                                            <span class="font-bold {{ $call->score_percent >= 80 ? 'text-emerald-600' : ($call->score_percent >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                                {{ number_format($call->score_percent, 1) }}%
                                            </span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($call->outcome === 'pass')
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Pass</span>
                                        @elseif ($call->outcome === 'fail')
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Fail</span>
                                        @elseif ($call->outcome === 'auto_fail')
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-200 text-red-800">Auto-fail</span>
                                        @else
                                            <span class="text-slate-300 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusMap = [
                                                'done'         => 'bg-emerald-100 text-emerald-700',
                                                'failed'       => 'bg-red-100 text-red-700',
                                                'queued'       => 'bg-slate-100 text-slate-600',
                                                'normalising'  => 'bg-amber-100 text-amber-700',
                                                'transcribing' => 'bg-blue-100 text-blue-700',
                                                'scoring'      => 'bg-indigo-100 text-indigo-700',
                                            ];
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusMap[$call->status] ?? 'bg-slate-100 text-slate-600' }}">
                                            {{ ucfirst($call->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-400 text-xs whitespace-nowrap">{{ $call->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('calls.show', $call) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold mr-3">View</a>
                                        <form method="POST" action="{{ route('calls.destroy', $call) }}"
                                              class="inline"
                                              onsubmit="return confirm('Delete this call and all its data?')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-400 hover:text-red-700 text-xs font-medium transition-colors">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($calls->hasPages())
                    <div class="px-4 py-3 border-t border-slate-100">
                        {{ $calls->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
