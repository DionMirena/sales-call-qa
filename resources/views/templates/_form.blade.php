{{-- Split-pane template builder. Receives optional $template for edit mode. --}}
@php
    $isEdit   = isset($template);
    $existing = $isEdit
        ? $template->criteria->map(fn($c) => [
            'name'           => $c->name,
            'description'    => $c->description,
            'fail_conditions'=> $c->fail_conditions ?? '',
            'type'           => $c->type,
            'weight'         => $c->weight,
            'is_critical'    => $c->is_critical,
            'phase_hint'     => $c->phase_hint,
          ])->values()->toArray()
        : [];
@endphp

@if($errors->any())
    <div class="mx-4 mt-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 shrink-0">
        <ul class="space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- Main split pane — merged x-data contains both criteriaBuilder rows AND mobileTab --}}
<div x-data="Object.assign(criteriaBuilder({{ json_encode($existing) }}), { mobileTab: 'builder' })"
     class="flex-1 flex flex-col min-h-0">

    {{-- Split panes --}}
    <div class="flex-1 flex flex-col lg:flex-row min-h-0">

        {{-- LEFT: Form builder --}}
        <div :class="mobileTab !== 'builder' ? 'hidden lg:flex' : 'flex'"
             class="flex-col lg:w-1/2 border-r border-slate-200 bg-white overflow-y-auto">

            {{-- Sticky top bar --}}
            <div class="sticky top-0 z-10 bg-white border-b border-slate-100 px-5 py-3 flex items-center justify-between shrink-0">
                <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">Form Builder</p>
                <div class="flex gap-2">
                    <a href="{{ route('templates.index') }}"
                       class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-4 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                        {{ $isEdit ? 'Save changes' : 'Create template' }}
                    </button>
                </div>
            </div>

            <div class="p-5 space-y-5">
                {{-- Template settings --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">
                            Template name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name"
                               value="{{ old('name', $template->name ?? '') }}"
                               required
                               class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                      focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white transition-colors"
                               placeholder="e.g. Standard Insurance QA">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">Description</label>
                        <textarea name="description" rows="2"
                                  class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                         focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white
                                         transition-colors resize-none">{{ old('description', $template->description ?? '') }}</textarea>
                    </div>
                    <div class="w-40">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">Pass threshold (%)</label>
                        <input type="number" name="pass_threshold" min="1" max="100"
                               value="{{ old('pass_threshold', $template->pass_threshold ?? 80) }}"
                               required
                               class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                      focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white transition-colors">
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold text-slate-600 uppercase tracking-wide">
                            Criteria <span class="text-slate-400 font-normal normal-case" x-text="'(' + rows.length + ')'"></span>
                        </h3>
                    </div>
                    @error('criteria')
                        <p class="mb-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @include('templates._criteria_builder', ['existing' => $existing])
            </div>
        </div>

        {{-- RIGHT: Live preview --}}
        <div :class="mobileTab !== 'preview' ? 'hidden lg:flex' : 'flex'"
             class="flex-col lg:w-1/2 bg-slate-50 overflow-y-auto">

            <div class="sticky top-0 z-10 bg-slate-100 border-b border-slate-200 px-5 py-3 flex items-center justify-between shrink-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Live Preview</p>
                <span class="px-2 py-0.5 bg-white text-slate-400 text-xs rounded-full border border-slate-200">Auto-updates</span>
            </div>

            <div class="p-5">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                        <p class="font-semibold text-slate-800 text-sm">QA Evaluation Form</p>
                        <p class="text-xs text-slate-400 mt-0.5">Preview — not the actual scoring form</p>
                    </div>

                    <template x-if="rows.length === 0">
                        <div class="py-12 text-center">
                            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center mx-auto mb-2">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <p class="text-sm text-slate-400">Add criteria to see the preview</p>
                        </div>
                    </template>

                    <div class="divide-y divide-slate-100">
                        <template x-for="(row, index) in rows" :key="row.key">
                            <div class="px-5 py-4">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center flex-wrap gap-1.5 mb-0.5">
                                            <span class="text-xs font-semibold text-slate-400" x-text="'Q' + (index + 1)"></span>
                                            <p class="text-sm font-semibold text-slate-800"
                                               x-text="row.name || 'Untitled criterion'"></p>
                                            <template x-if="row.is_critical">
                                                <span class="px-1.5 py-0.5 bg-red-50 text-red-600 text-xs font-bold rounded border border-red-200">
                                                    AUTO-FAIL
                                                </span>
                                            </template>
                                        </div>
                                        <p class="text-xs text-slate-500 leading-snug"
                                           x-text="row.description || 'No description yet'"></p>
                                    </div>
                                    <span class="text-xs font-bold text-indigo-600 shrink-0"
                                          x-text="row.weight + (row.weight == 1 ? ' pt' : ' pts')"></span>
                                </div>

                                {{-- Response options by type --}}
                                <div class="space-y-1.5 pl-2">
                                    <template x-if="row.type === 'pass_fail'">
                                        <div class="flex gap-4">
                                            <label class="flex items-center gap-1.5 text-sm text-slate-600">
                                                <input type="radio" disabled class="accent-emerald-500"> Pass
                                            </label>
                                            <label class="flex items-center gap-1.5 text-sm text-slate-600">
                                                <input type="radio" disabled class="accent-red-500"> Fail
                                            </label>
                                        </div>
                                    </template>
                                    <template x-if="row.type === 'yes_no_na'">
                                        <div class="flex gap-4">
                                            <label class="flex items-center gap-1.5 text-sm text-slate-600"><input type="radio" disabled> Yes</label>
                                            <label class="flex items-center gap-1.5 text-sm text-slate-600"><input type="radio" disabled> No</label>
                                            <label class="flex items-center gap-1.5 text-sm text-slate-600"><input type="radio" disabled> N/A</label>
                                        </div>
                                    </template>
                                    <template x-if="row.type === 'scale_1_5'">
                                        <div class="flex items-center gap-3">
                                            <template x-for="n in [1,2,3,4,5]">
                                                <label class="flex flex-col items-center gap-1 text-xs text-slate-500">
                                                    <input type="radio" disabled>
                                                    <span x-text="n"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="row.type === 'extract'">
                                        <div class="bg-slate-50 rounded-lg px-3 py-2 text-xs text-slate-400 italic border border-slate-200">
                                            AI extracts text from transcript
                                        </div>
                                    </template>
                                </div>

                                <p class="text-xs text-slate-300 mt-2 capitalize"
                                   x-text="'Phase: ' + (row.phase_hint || 'anywhere')"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile tab bar --}}
    <div class="lg:hidden flex border-t border-slate-200 bg-white shrink-0">
        <button type="button"
                @click="mobileTab = 'builder'"
                :class="mobileTab === 'builder' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'"
                class="flex-1 py-3 text-sm font-semibold border-t-2 transition-colors">
            Builder
        </button>
        <button type="button"
                @click="mobileTab = 'preview'"
                :class="mobileTab === 'preview' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'"
                class="flex-1 py-3 text-sm font-semibold border-t-2 transition-colors">
            Live Preview
        </button>
    </div>
</div>
