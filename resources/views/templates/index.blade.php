<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-base font-semibold text-slate-800">Templates</h1>
            <p class="text-xs text-slate-400 hidden sm:block">QA evaluation templates</p>
        </div>
        <a href="{{ route('templates.create') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">New Template</span>
        </a>
    </x-slot>

    <div class="p-4 sm:p-6 max-w-5xl mx-auto w-full">

        @if(session('success'))
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            @forelse($templates as $template)
                <div class="flex items-center gap-4 px-5 py-4 border-b border-slate-50 last:border-0 hover:bg-slate-50 transition-colors group">
                    <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('templates.show', $template) }}"
                           class="font-semibold text-slate-800 hover:text-indigo-600 transition-colors">
                            {{ $template->name }}
                        </a>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $template->criteria_count }} {{ Str::plural('criterion', $template->criteria_count) }}
                            · Pass ≥ {{ $template->pass_threshold }}%
                            · by {{ $template->creator->name }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="{{ route('templates.edit', $template) }}"
                           class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors">
                            Edit
                        </a>
                        <form method="POST" action="{{ route('templates.duplicate', $template) }}">
                            @csrf
                            <button class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors">
                                Duplicate
                            </button>
                        </form>
                        <form method="POST" action="{{ route('templates.destroy', $template) }}"
                              onsubmit="return confirm('Delete this template?')">
                            @csrf @method('DELETE')
                            <button class="px-3 py-1.5 bg-red-50 text-red-600 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="py-16 text-center">
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700 mb-1">No templates yet</p>
                    <p class="text-sm text-slate-400 mb-4">Create your first QA evaluation template.</p>
                    <a href="{{ route('templates.create') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm rounded-xl hover:bg-indigo-700 transition-colors">
                        Create template
                    </a>
                </div>
            @endforelse
        </div>

        @if ($templates->hasPages())
            <div class="mt-4">{{ $templates->links() }}</div>
        @endif
    </div>
</x-app-layout>
