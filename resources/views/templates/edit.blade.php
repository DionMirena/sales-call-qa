<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Edit: {{ $template->name }}</h2>
    </x-slot>

    <div class="py-6 max-w-4xl mx-auto px-4">
        <form method="POST" action="{{ route('templates.update', $template) }}">
            @csrf @method('PUT')
            @include('templates._form', ['template' => $template])
        </form>
    </div>
</x-app-layout>
