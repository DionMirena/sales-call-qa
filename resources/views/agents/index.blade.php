<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Agents</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                @if ($agents->isEmpty())
                    <div class="px-6 py-12 text-center text-gray-500 text-sm">No agents found.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Name</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Team</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Total Calls</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Completed</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($agents as $agent)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $agent->name }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $agent->team ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $agent->total_calls_count }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $agent->done_calls_count }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('agents.show', $agent) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View stats</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($agents->hasPages())
                        <div class="px-4 py-3 border-t border-gray-200">
                            {{ $agents->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
