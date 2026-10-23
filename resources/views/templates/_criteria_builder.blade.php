{{-- Alpine.js criteria builder. Expects $existing (array of criteria for editing) or nothing for create. --}}
<div
    x-data="criteriaBuilder({{ json_encode($existing ?? []) }})"
    class="space-y-3"
>
    <template x-for="(row, index) in rows" :key="row.key">
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-gray-700" x-text="'Criterion ' + (index + 1)"></span>
                <div class="flex gap-2">
                    <button type="button" @click="moveUp(index)"
                            :disabled="index === 0"
                            class="text-xs px-2 py-1 bg-white border rounded hover:bg-gray-100 disabled:opacity-30">↑</button>
                    <button type="button" @click="moveDown(index)"
                            :disabled="index === rows.length - 1"
                            class="text-xs px-2 py-1 bg-white border rounded hover:bg-gray-100 disabled:opacity-30">↓</button>
                    <button type="button" @click="remove(index)"
                            class="text-xs px-2 py-1 bg-red-50 border border-red-200 text-red-600 rounded hover:bg-red-100">
                        Remove
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3">
                {{-- Name --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text"
                           :name="'criteria[' + index + '][name]'"
                           x-model="row.name"
                           required
                           class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="e.g. Agent Identification">
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Description — what good looks like <span class="text-red-500">*</span></label>
                    <textarea
                        :name="'criteria[' + index + '][description]'"
                        x-model="row.description"
                        required
                        rows="2"
                        class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Agent clearly states their full name and the company name..."></textarea>
                </div>

                {{-- Fail conditions --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Fail conditions (optional)</label>
                    <textarea
                        :name="'criteria[' + index + '][fail_conditions]'"
                        x-model="row.fail_conditions"
                        rows="1"
                        class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Agent does not state name OR does not state company name."></textarea>
                </div>

                {{-- Type, Weight, Phase, Critical in one row --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                        <select :name="'criteria[' + index + '][type]'" x-model="row.type"
                                class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="pass_fail">pass / fail</option>
                            <option value="yes_no_na">yes / no / N/A</option>
                            <option value="scale_1_5">scale 1–5</option>
                            <option value="extract">extract (info only)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Weight (0–10)</label>
                        <input type="number" min="0" max="10"
                               :name="'criteria[' + index + '][weight]'"
                               x-model.number="row.weight"
                               class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Phase hint</label>
                        <select :name="'criteria[' + index + '][phase_hint]'" x-model="row.phase_hint"
                                class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="anywhere">anywhere</option>
                            <option value="opening">opening</option>
                            <option value="discovery">discovery</option>
                            <option value="pitch">pitch</option>
                            <option value="objections">objections</option>
                            <option value="close">close</option>
                        </select>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="hidden" :name="'criteria[' + index + '][is_critical]'" value="0">
                            <input type="checkbox"
                                   :name="'criteria[' + index + '][is_critical]'"
                                   x-model="row.is_critical"
                                   value="1"
                                   class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            <span class="text-xs font-medium text-gray-700">⚠ Critical</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <button type="button" @click="add()"
            class="w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition">
        + Add criterion
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
