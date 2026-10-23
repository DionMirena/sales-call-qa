{{-- Shared form partial for create and edit. --}}
@php
    $isEdit = isset($template);
    $existing = $isEdit
        ? $template->criteria->map(fn($c) => [
            'name' => $c->name,
            'description' => $c->description,
            'fail_conditions' => $c->fail_conditions ?? '',
            'type' => $c->type,
            'weight' => $c->weight,
            'is_critical' => $c->is_critical,
            'phase_hint' => $c->phase_hint,
          ])->values()->toArray()
        : [];
@endphp

@if($errors->any())
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-white shadow rounded p-5 mb-6 space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Template name <span class="text-red-500">*</span></label>
        <input type="text" name="name"
               value="{{ old('name', $template->name ?? '') }}"
               required
               class="w-full border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea name="description" rows="2"
                  class="w-full border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $template->description ?? '') }}</textarea>
    </div>
    <div class="w-40">
        <label class="block text-sm font-medium text-gray-700 mb-1">Pass threshold (%)</label>
        <input type="number" name="pass_threshold" min="1" max="100"
               value="{{ old('pass_threshold', $template->pass_threshold ?? 80) }}"
               required
               class="w-full border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
    </div>
</div>

<div class="mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Criteria</h3>
    @include('templates._criteria_builder', ['existing' => $existing])
    @error('criteria')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="flex gap-3">
    <button type="submit"
            class="px-5 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm font-medium">
        {{ $isEdit ? 'Save changes' : 'Create template' }}
    </button>
    <a href="{{ route('templates.index') }}"
       class="px-5 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 text-sm">
        Cancel
    </a>
</div>
