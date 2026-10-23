<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">New Template</h2>
    </x-slot>

    <div class="py-6 max-w-4xl mx-auto px-4">
        <form method="POST" action="{{ route('templates.store') }}">
            @csrf
            @include('templates._form')
        </form>
    </div>
</x-app-layout>
