<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ $template->name }}</h2>
            <div class="flex gap-3">
                <a href="{{ route('templates.edit', $template) }}"
                   class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">Edit</a>
                <form method="POST" action="{{ route('templates.duplicate', $template) }}">
                    @csrf
                    <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 text-sm">
                        Duplicate
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-5xl mx-auto px-4">

        <div class="bg-white shadow rounded p-5 mb-6">
            <dl class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Pass threshold</dt>
                    <dd class="font-medium">{{ $template->pass_threshold }}%</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Criteria</dt>
                    <dd class="font-medium">{{ $template->criteria->count() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Created by</dt>
                    <dd class="font-medium">{{ $template->creator->name }}</dd>
                </div>
            </dl>
            @if($template->description)
                <p class="mt-3 text-sm text-gray-600">{{ $template->description }}</p>
            @endif
        </div>

        <div class="bg-white shadow rounded overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Weight</th>
                        <th class="px-4 py-3 text-left">Phase</th>
                        <th class="px-4 py-3 text-left">Critical</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($template->criteria as $criterion)
                        <tr class="border-t {{ $criterion->is_critical ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $criterion->name }}</p>
                                <p class="text-gray-500 text-xs mt-0.5">{{ Str::limit($criterion->description, 80) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-mono bg-gray-100">
                                    {{ $criterion->type }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $criterion->weight }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $criterion->phase_hint }}</td>
                            <td class="px-4 py-3">
                                @if($criterion->is_critical)
                                    <span class="text-red-600 font-semibold">⚠ Yes</span>
                                @else
                                    <span class="text-gray-400">No</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
