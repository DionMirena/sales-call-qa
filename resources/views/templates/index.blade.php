<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">QA Templates</h2>
            <a href="{{ route('templates.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
                + New Template
            </a>
        </div>
    </x-slot>

    <div class="py-6 max-w-5xl mx-auto px-4">

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
        @endif

        <div class="bg-white shadow rounded">
            @forelse($templates as $template)
                <div class="flex items-center justify-between px-5 py-4 border-b last:border-0">
                    <div>
                        <a href="{{ route('templates.show', $template) }}"
                           class="font-medium text-indigo-700 hover:underline">
                            {{ $template->name }}
                        </a>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ $template->criteria_count }} criteria &middot;
                            Pass ≥ {{ $template->pass_threshold }}% &middot;
                            by {{ $template->creator->name }}
                        </p>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <a href="{{ route('templates.edit', $template) }}"
                           class="text-gray-600 hover:text-gray-900">Edit</a>
                        <form method="POST" action="{{ route('templates.duplicate', $template) }}">
                            @csrf
                            <button class="text-gray-600 hover:text-gray-900">Duplicate</button>
                        </form>
                        <form method="POST" action="{{ route('templates.destroy', $template) }}"
                              onsubmit="return confirm('Delete this template?')">
                            @csrf @method('DELETE')
                            <button class="text-red-500 hover:text-red-700">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-gray-400">No templates yet. Create one above.</p>
            @endforelse
        </div>

        <div class="mt-4">{{ $templates->links() }}</div>
    </div>
</x-app-layout>
