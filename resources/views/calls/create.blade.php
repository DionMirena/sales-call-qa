<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 min-w-0">
            <a href="{{ route('calls.index') }}"
               class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-base font-semibold text-slate-800 truncate">Upload Call Recording</h1>
        </div>
    </x-slot>

    <div class="p-4 sm:p-6 max-w-2xl mx-auto w-full">

        @if($errors->any())
            <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-2xl">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <ul class="text-sm text-red-700 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('calls.store') }}" enctype="multipart/form-data"
              x-data="uploadZone()">
            @csrf

            <div class="space-y-4">

                {{-- Drag-drop upload zone --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                    <label class="block text-sm font-semibold text-slate-700 mb-3">
                        Audio Recording <span class="text-red-500">*</span>
                    </label>

                    <div @dragover.prevent="dragging = true"
                         @dragleave.prevent="dragging = false"
                         @drop.prevent="handleDrop($event)"
                         :class="dragging ? 'border-indigo-400 bg-indigo-50' : (fileName ? 'border-emerald-400 bg-emerald-50' : 'border-slate-300 hover:border-indigo-300 hover:bg-indigo-50/40')"
                         class="border-2 border-dashed rounded-xl p-8 text-center transition-colors duration-150 cursor-pointer"
                         @click="$refs.fileInput.click()">

                        <template x-if="!fileName">
                            <div>
                                <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-slate-700">Drop your audio file here</p>
                                <p class="text-xs text-slate-400 mt-1">or click to browse</p>
                                <p class="text-xs text-slate-300 mt-3">MP3 · WAV · M4A · AAC · OGG · FLAC · WebM · up to 1 GB</p>
                            </div>
                        </template>

                        <template x-if="fileName">
                            <div>
                                <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-slate-700" x-text="fileName"></p>
                                <p class="text-xs text-emerald-600 mt-1">Ready to upload · <span class="underline cursor-pointer" @click.stop="clearFile()">Change file</span></p>
                            </div>
                        </template>

                        <input type="file"
                               name="audio"
                               x-ref="fileInput"
                               @change="handleFile($event)"
                               accept="audio/*,video/mp4,video/webm"
                               class="hidden">
                    </div>

                    @error('audio')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Call details --}}
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
                    <h3 class="text-sm font-semibold text-slate-700">Call Details</h3>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">
                            QA Template <span class="text-red-500">*</span>
                        </label>
                        <select name="template_id" required
                                class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                       focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white
                                       transition-colors">
                            <option value="">— select a template —</option>
                            @foreach($templates as $t)
                                <option value="{{ $t->id }}" {{ old('template_id') == $t->id ? 'selected' : '' }}>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">
                            Agent <span class="text-red-500">*</span>
                        </label>
                        <select name="agent_id" required
                                class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                       focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white
                                       transition-colors">
                            <option value="">— select an agent —</option>
                            @foreach($agents as $a)
                                <option value="{{ $a->id }}" {{ old('agent_id') == $a->id ? 'selected' : '' }}>
                                    {{ $a->name }}{{ $a->team ? ' (' . $a->team . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">Customer Name</label>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}"
                                   class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                          focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white
                                          transition-colors"
                                   placeholder="Jane Smith">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">Policy Type</label>
                            <input type="text" name="policy_type" value="{{ old('policy_type') }}"
                                   class="w-full text-sm border-slate-200 rounded-xl bg-slate-50
                                          focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white
                                          transition-colors"
                                   placeholder="Level term, whole life…">
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3">
                    <button type="submit"
                            :disabled="!fileName"
                            :class="fileName
                                ? 'bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer'
                                : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                            class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl text-sm font-semibold transition-colors">
                        Upload and Process
                    </button>
                    <a href="{{ route('calls.index') }}"
                       class="px-6 py-2.5 bg-slate-100 text-slate-700 rounded-xl text-sm font-medium hover:bg-slate-200 transition-colors">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
    function uploadZone() {
        return {
            dragging: false,
            fileName: null,
            clearFile() {
                this.fileName = null;
                this.$refs.fileInput.value = '';
            },
            handleFile(e) {
                const file = e.target.files[0];
                if (file) this.fileName = file.name;
            },
            handleDrop(e) {
                this.dragging = false;
                const file = e.dataTransfer.files[0];
                if (!file) return;
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.fileInput.files = dt.files;
                this.fileName = file.name;
            },
        };
    }
    </script>
</x-app-layout>
