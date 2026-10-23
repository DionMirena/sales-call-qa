{{-- Criteria builder rows. Must live inside criteriaBuilder() x-data scope. --}}
<div class="space-y-3">
    <template x-for="(row, index) in rows" :key="row.key">
        <div class="border border-slate-200 rounded-xl bg-white overflow-hidden">

            {{-- Row header --}}
            <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-400 uppercase" x-text="'Criterion ' + (index + 1)"></span>
                    <template x-if="row.is_critical">
                        <span class="px-1.5 py-0.5 bg-red-50 text-red-600 text-xs font-semibold rounded border border-red-200">CRITICAL</span>
                    </template>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" @click="moveUp(index)"
                            :disabled="index === 0"
                            class="p-1 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-200 disabled:opacity-30 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    </button>
                    <button type="button" @click="moveDown(index)"
                            :disabled="index === rows.length - 1"
                            class="p-1 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-200 disabled:opacity-30 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <button type="button" @click="remove(index)"
                            class="p-1 rounded text-red-400 hover:text-red-700 hover:bg-red-50 transition-colors ml-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="p-4 space-y-3">
                {{-- Name --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase tracking-wide">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           :name="'criteria[' + index + '][name]'"
                           x-model="row.name"
                           required
                           class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                  focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white transition-colors"
                           placeholder="e.g. Recording Disclosure">
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase tracking-wide">
                        Description — what good looks like <span class="text-red-500">*</span>
                    </label>
                    <textarea :name="'criteria[' + index + '][description]'"
                              x-model="row.description"
                              required
                              rows="2"
                              class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                     focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white
                                     transition-colors resize-none"
                              placeholder="Agent clearly states their full name and the company…"></textarea>
                </div>

                {{-- Fail conditions --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase tracking-wide">
                        Fail conditions <span class="text-slate-300 font-normal normal-case">(optional)</span>
                    </label>
                    <textarea :name="'criteria[' + index + '][fail_conditions]'"
                              x-model="row.fail_conditions"
                              rows="1"
                              class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                     focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white
                                     transition-colors resize-none"
                              placeholder="Agent does not state name OR does not state company name"></textarea>
                </div>

                {{-- Type / Weight / Phase / Critical --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase tracking-wide">Type <span class="text-red-500">*</span></label>
                        <select :name="'criteria[' + index + '][type]'" x-model="row.type"
                                class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                       focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white transition-colors">
                            <option value="pass_fail">pass / fail</option>
                            <option value="yes_no_na">yes / no / N/A</option>
                            <option value="scale_1_5">scale 1–5</option>
                            <option value="extract">extract</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase tracking-wide">Weight</label>
                        <input type="number" min="0" max="10"
                               :name="'criteria[' + index + '][weight]'"
                               x-model.number="row.weight"
                               class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                      focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase tracking-wide">Phase</label>
                        <select :name="'criteria[' + index + '][phase_hint]'" x-model="row.phase_hint"
                                class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                       focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white transition-colors">
                            <option value="anywhere">anywhere</option>
                            <option value="opening">opening</option>
                            <option value="discovery">discovery</option>
                            <option value="pitch">pitch</option>
                            <option value="objections">objections</option>
                            <option value="close">close</option>
                        </select>
                    </div>
                    <div class="flex items-end pb-0.5">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" :name="'criteria[' + index + '][is_critical]'" value="0">
                            <input type="checkbox"
                                   :name="'criteria[' + index + '][is_critical]'"
                                   x-model="row.is_critical"
                                   value="1"
                                   class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                            <span class="text-xs font-semibold text-slate-600">⚠ Critical</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <button type="button" @click="add()"
            class="w-full py-3 border-2 border-dashed border-slate-200 rounded-xl text-sm text-slate-400
                   hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50/40 transition-all duration-150
                   flex items-center justify-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add criterion
    </button>
</div>

<script>
function criteriaBuilder(existing) {
    return {
        rows: existing.length ? existing.map((r, i) => ({ ...r, key: i })) : [],
        nextKey: existing.length,
        add() {
            this.rows.push({
                key: this.nextKey++,
                name: '', description: '', fail_conditions: '',
                type: 'pass_fail', weight: 1, is_critical: false, phase_hint: 'anywhere',
            });
        },
        remove(index) {
            this.rows.splice(index, 1);
        },
        moveUp(index) {
            if (index > 0) {
                [this.rows[index - 1], this.rows[index]] = [this.rows[index], this.rows[index - 1]];
                this.rows = [...this.rows];
            }
        },
        moveDown(index) {
            if (index < this.rows.length - 1) {
                [this.rows[index], this.rows[index + 1]] = [this.rows[index + 1], this.rows[index]];
                this.rows = [...this.rows];
            }
        },
    };
}
</script>
