<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Upload a Call Recording</h2>
    </x-slot>

    <div class="py-6 max-w-2xl mx-auto px-4">

        @if($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('calls.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="bg-white shadow rounded p-6 space-y-5">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">QA Template <span class="text-red-500">*</span></label>
                    <select name="template_id" required
                            class="w-full border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">— select a template —</option>
                        @foreach($templates as $t)
                            <option value="{{ $t->id }}" {{ old('template_id') == $t->id ? 'selected' : '' }}>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Agent <span class="text-red-500">*</span></label>
                    <select name="agent_id" required
                            class="w-full border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">— select an agent —</option>
                        @foreach($agents as $a)
                            <option value="{{ $a->id }}" {{ old('agent_id') == $a->id ? 'selected' : '' }}>
                                {{ $a->name }}{{ $a->team ? ' (' . $a->team . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer name (optional)</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}"
                               class="w-full border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Jane Smith">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Policy type (optional)</label>
                        <input type="text" name="policy_type" value="{{ old('policy_type') }}"
                               class="w-full border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Level term, whole life…">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Audio file <span class="text-red-500">*</span>
                        <span class="font-normal text-gray-400 ml-1">(mp3, wav, m4a, aac, ogg, flac, webm, mp4 — max 1 GB)</span>
                    </label>
                    <input type="file" name="audio" required accept="audio/*,video/mp4,video/webm"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    @error('audio')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <div class="mt-4 flex gap-3">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm font-medium">
                    Upload and process
                </button>
                <a href="{{ route('dashboard') }}"
                   class="px-6 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 text-sm">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
