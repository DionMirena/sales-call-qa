<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
            <a href="{{ route('calls.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                + Upload Call
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Today stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Calls Processed Today</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $todayDone }}</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Score Today</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">
                        @if ($todayAvgScore !== null)
                            {{ number_format($todayAvgScore, 1) }}%
                        @else
                            <span class="text-gray-400 text-2xl">—</span>
                        @endif
                    </p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Needs Review</p>
                    <p class="mt-1 text-3xl font-bold {{ $needsReviewCount > 0 ? 'text-amber-600' : 'text-gray-900' }}">
                        {{ $needsReviewCount }}
                    </p>
                </div>
            </div>

            {{-- Needs-review queue --}}
            @if ($needsReviewCalls->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Needs Review</h3>
                        <a href="{{ route('calls.index', ['status' => 'done']) }}"
                           class="text-xs text-indigo-600 hover:underline">See all calls</a>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Agent</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Template</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Score</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Review Items</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($needsReviewCalls as $call)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $call->agent->name }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $call->template->name }}</td>
                                    <td class="px-4 py-2">
                                        @if ($call->score_percent !== null)
                                            {{ number_format($call->score_percent, 1) }}%
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                            {{ $call->needs_review_count }} {{ Str::plural('item', $call->needs_review_count) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('calls.show', $call) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Review</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                    <p class="text-gray-500 text-sm">No calls need review right now.</p>
                    <div class="mt-3 space-x-3">
                        <a href="{{ route('calls.index') }}" class="text-sm text-indigo-600 hover:underline">View all calls</a>
                        <a href="{{ route('calls.create') }}" class="text-sm text-indigo-600 hover:underline">Upload a call</a>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
