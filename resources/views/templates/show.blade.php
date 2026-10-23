<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 min-w-0">
            <a href="{{ route('templates.index') }}"
               class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-base font-semibold text-slate-800 truncate">{{ $template->name }}</h1>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('templates.edit', $template) }}"
               class="px-3.5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors">
                Edit
            </a>
            <form method="POST" action="{{ route('templates.duplicate', $template) }}">
                @csrf
                <button class="px-3.5 py-2 bg-slate-100 text-slate-700 text-sm font-medium rounded-xl hover:bg-slate-200 transition-colors">
                    Duplicate
                </button>
            </form>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 max-w-5xl mx-auto w-full space-y-4">

        {{-- Summary --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Pass threshold</p>
                    <p class="font-semibold text-slate-800">{{ $template->pass_threshold }}%</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Criteria</p>
                    <p class="font-semibold text-slate-800">{{ $template->criteria->count() }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Created by</p>
                    <p class="font-semibold text-slate-800">{{ $template->creator->name }}</p>
                </div>
            </div>
            @if($template->description)
                <p class="mt-3 text-sm text-slate-600 border-t border-slate-100 pt-3">{{ $template->description }}</p>
            @endif
        </div>

        {{-- Criteria table --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            @if($template->criteria->isEmpty())
                <div class="py-12 text-center text-slate-400 text-sm">No criteria on this template.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Weight</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Phase</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Critical</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($template->criteria as $criterion)
                                <tr class="{{ $criterion->is_critical ? 'bg-red-50/40' : 'hover:bg-slate-50' }} transition-colors">
                                    <td class="px-4 py-3 text-slate-400 text-xs">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-800">{{ $criterion->name }}</p>
                                        <p class="text-slate-500 text-xs mt-0.5 leading-snug">{{ Str::limit($criterion->description, 80) }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-lg text-xs font-mono bg-slate-100 text-slate-600">
                                            {{ $criterion->type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-slate-700">{{ $criterion->weight }}</td>
                                    <td class="px-4 py-3 text-slate-500 text-xs capitalize">{{ $criterion->phase_hint }}</td>
                                    <td class="px-4 py-3">
                                        @if($criterion->is_critical)
                                            <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-semibold rounded-md">⚠ Critical</span>
                                        @else
                                            <span class="text-slate-300 text-xs">—</span>
                                        @endif
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
