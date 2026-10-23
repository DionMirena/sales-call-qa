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
            <span class="px-2 py-0.5 bg-slate-100 text-slate-500 text-xs font-semibold rounded-full shrink-0">Editing</span>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('templates.update', $template) }}"
          class="h-[calc(100vh-3.5rem)] flex flex-col">
        @csrf @method('PUT')
        @include('templates._form', ['template' => $template])
    </form>
</x-app-layout>
