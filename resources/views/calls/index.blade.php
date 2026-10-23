<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Calls</h2>
            <a href="{{ route('calls.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                + Upload Call
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Filters --}}
            <form method="GET" action="{{ route('calls.index') }}"
                  class="bg-white shadow-sm rounded-lg p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Agent</label>
                        <select name="agent_id"
                                class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All agents</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}"
                                    {{ request('agent_id') == $agent->id ? 'selected' : '' }}>
                                    {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Template</label>
                        <select name="template_id"
                                class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All templates</option>
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}"
                                    {{ request('template_id') == $tpl->id ? 'selected' : '' }}>
                                    {{ $tpl->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Outcome</label>
                        <select name="outcome"
                                class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All outcomes</option>
                            <option value="pass" {{ request('outcome') === 'pass' ? 'selected' : '' }}>Pass</option>
                            <option value="fail" {{ request('outcome') === 'fail' ? 'selected' : '' }}>Fail</option>
                            <option value="auto_fail" {{ request('outcome') === 'auto_fail' ? 'selected' : '' }}>Auto-fail</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select name="status"
                                class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All statuses</option>
                            <option value="done" {{ request('status') === 'done' ? 'selected' : '' }}>Done</option>
                            <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>Queued</option>
                            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                               class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button type="submit"
                            class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                        Filter
                    </button>
                    <a href="{{ route('calls.index') }}"
                       class="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                        Clear
                    </a>
                    <span class="text-xs text-gray-500 ms-auto">
                        {{ $calls->total() }} {{ Str::plural('call', $calls->total()) }}
                    </span>
                </div>
            </form>

            {{-- Table --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                @if ($calls->isEmpty())
                    <div class="px-6 py-12 text-center text-gray-500 text-sm">
                        No calls found. <a href="{{ route('calls.create') }}" class="text-indigo-600 hover:underline">Upload the first one</a>.
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Agent</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Template</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Score</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Outcome</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Uploaded</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($calls as $call)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a href="{{ route('agents.show', $call->agent) }}"
                                           class="hover:text-indigo-600">{{ $call->agent->name }}</a>
                                        @if ($call->agent->team)
                                            <span class="text-xs text-gray-400 block">{{ $call->agent->team }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $call->template->name }}</td>
                                    <td class="px-4 py-3 font-semibold">
                                        @if ($call->score_percent !== null)
                                            {{ number_format($call->score_percent, 1) }}%
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($call->outcome === 'pass')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Pass</span>
                                        @elseif ($call->outcome === 'fail')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Fail</span>
                                        @elseif ($call->outcome === 'auto_fail')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Auto-fail</span>
                                        @else
                                            <span class="text-gray-400 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusColors = [
                                                'done'         => 'bg-green-100 text-green-800',
                                                'failed'       => 'bg-red-100 text-red-800',
                                                'queued'       => 'bg-gray-100 text-gray-700',
                                                'normalising'  => 'bg-yellow-100 text-yellow-800',
                                                'transcribing' => 'bg-blue-100 text-blue-800',
                                                'scoring'      => 'bg-purple-100 text-purple-800',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$call->status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ ucfirst($call->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $call->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('calls.show', $call) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium me-3">View</a>

                                        <form method="POST" action="{{ route('calls.destroy', $call) }}"
                                              class="inline"
                                              onsubmit="return confirm('Delete this call and all its data?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-500 hover:text-red-700 text-xs font-medium">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($calls->hasPages())
                        <div class="px-4 py-3 border-t border-gray-200">
                            {{ $calls->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
