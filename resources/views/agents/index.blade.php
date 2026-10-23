<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-base font-semibold text-slate-800">Agents</h1>
            <p class="text-xs text-slate-400 hidden sm:block">{{ $agents->total() }} {{ Str::plural('agent', $agents->total()) }}</p>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 max-w-5xl mx-auto w-full">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            @if ($agents->isEmpty())
                <div class="py-16 text-center">
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">No agents found</p>
                    <p class="text-sm text-slate-400 mt-1">Agents are created when you seed the database.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Agent</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Team</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Calls</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Completed</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($agents as $agent)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-xs font-bold text-indigo-700 shrink-0">
                                                {{ strtoupper(substr($agent->name, 0, 2)) }}
                                            </div>
                                            <span class="font-semibold text-slate-800">{{ $agent->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">{{ $agent->team ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 font-medium">{{ $agent->total_calls_count }}</td>
                                    <td class="px-4 py-3 text-slate-600 font-medium">{{ $agent->done_calls_count }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('agents.show', $agent) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold">
                                            View stats →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($agents->hasPages())
                    <div class="px-4 py-3 border-t border-slate-100">
                        {{ $agents->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
