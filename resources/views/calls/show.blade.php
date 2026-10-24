<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 min-w-0 flex-1">
            <a href="{{ route('calls.index') }}"
               class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="text-sm font-semibold text-slate-800 truncate">
                    <span class="text-indigo-600">Call #{{ $call->id }}</span> · {{ $call->original_filename }}
                </h1>
                <div class="flex items-center gap-1.5 text-xs text-slate-400 mt-0.5">
                    <span class="font-medium text-slate-600">{{ $call->agent->name }}</span>
                    <span>·</span>
                    <span class="truncate">{{ $call->template->name }}</span>
                    @if($call->customer_name)
                        <span>·</span>
                        <span>{{ $call->customer_name }}</span>
                    @endif
                </div>
            </div>
        </div>
        {{-- Header actions --}}
        <div class="flex items-center gap-2 shrink-0">
            @if($call->status === 'done')
                @php
                    $outcomeColors = [
                        'pass'      => 'bg-emerald-100 text-emerald-700',
                        'auto_fail' => 'bg-red-100 text-red-700',
                        'fail'      => 'bg-red-100 text-red-700',
                    ];
                @endphp
                <span class="hidden sm:inline-flex px-3 py-1 rounded-full text-xs font-bold
                             {{ $outcomeColors[$call->outcome] ?? 'bg-slate-100 text-slate-600' }}">
                    {{ strtoupper(str_replace('_', '-', $call->outcome)) }}
                </span>
                <span class="hidden sm:inline-flex px-3 py-1 rounded-full text-xs font-bold
                             {{ $call->score_percent >= 80 ? 'bg-emerald-100 text-emerald-700' : ($call->score_percent >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                    {{ number_format($call->score_percent, 0) }}%
                </span>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors">
                        Re-score ▾
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 top-full mt-1 w-64 bg-white rounded-xl shadow-lg border border-slate-100 p-3 z-10">
                        <form method="POST" action="{{ route('calls.rescore', $call) }}"
                              onsubmit="return confirm('Re-score? Existing scores and overrides will be cleared. The transcript is kept.')">
                            @csrf
                            <label class="block text-xs font-medium text-slate-500 mb-1">Template (optional)</label>
                            <select name="template_id"
                                    class="w-full text-xs border-slate-200 rounded-lg bg-slate-50 mb-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">{{ $call->template->name }} (current)</option>
                                @foreach(\App\Models\Template::orderBy('name')->get(['id','name']) as $tpl)
                                    @if($tpl->id !== $call->template_id)
                                        <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="submit"
                                    class="w-full px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                Re-score now
                            </button>
                        </form>
                    </div>
                </div>
            @endif
            <form method="POST" action="{{ route('calls.destroy', $call) }}"
                  onsubmit="return confirm('Delete this call and all its data?')">
                @csrf @method('DELETE')
                <button class="px-3 py-1.5 bg-red-50 text-red-600 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors">
                    Delete
                </button>
            </form>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mx-4 mt-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Processing state ─────────────────────────────────────────────────── --}}
    @if($call->status !== 'done')
        <div class="p-4 sm:p-6 max-w-2xl mx-auto w-full"
             x-data="callStatus('{{ $call->status }}', '{{ route('calls.status', $call) }}')">

            {{-- Step tracker --}}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-4">
                <h3 class="text-sm font-semibold text-slate-700 mb-6">Processing</h3>

                @php
                    $steps = [
                        ['key' => 'queued',       'label' => 'Queued',      'desc' => 'Waiting for a worker'],
                        ['key' => 'normalising',  'label' => 'Normalising', 'desc' => 'Converting audio with ffmpeg'],
                        ['key' => 'transcribing', 'label' => 'Transcribing','desc' => 'Sending to Deepgram'],
                        ['key' => 'scoring',      'label' => 'Scoring',     'desc' => 'Analysing with AI'],
                        ['key' => 'done',         'label' => 'Complete',    'desc' => 'Results ready'],
                    ];
                    $stepOrder = ['queued'=>0,'normalising'=>1,'transcribing'=>2,'scoring'=>3,'done'=>4,'failed'=>4];
                @endphp

                <div class="relative">
                    {{-- Connector line --}}
                    <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-slate-100"></div>

                    <div class="space-y-6">
                        @foreach ($steps as $i => $step)
                            @php
                                $statusIndex = $stepOrder[$call->status] ?? 0;
                                $stepIndex = $i;
                                $isDone    = $statusIndex > $stepIndex;
                                $isActive  = $statusIndex === $stepIndex && $call->status !== 'failed';
                                $isFailed  = $call->status === 'failed' && $stepIndex === 3;
                            @endphp
                            <div class="flex items-start gap-4 relative">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 z-10
                                            {{ $isDone   ? 'bg-emerald-500'  : '' }}
                                            {{ $isActive ? 'bg-indigo-600 ring-4 ring-indigo-100' : '' }}
                                            {{ $isFailed ? 'bg-red-500'      : '' }}
                                            {{ !$isDone && !$isActive && !$isFailed ? 'bg-slate-100' : '' }}">
                                    @if($isDone)
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @elseif($isActive)
                                        <svg class="w-3.5 h-3.5 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                        </svg>
                                    @elseif($isFailed)
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    @else
                                        <div class="w-2 h-2 bg-slate-300 rounded-full"></div>
                                    @endif
                                </div>
                                <div class="pt-1">
                                    <p class="text-sm font-semibold {{ $isActive ? 'text-indigo-700' : ($isDone ? 'text-slate-800' : 'text-slate-400') }}">
                                        {{ $step['label'] }}
                                    </p>
                                    <p class="text-xs {{ $isActive ? 'text-indigo-500' : 'text-slate-400' }}">
                                        {{ $step['desc'] }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($call->status === 'failed' && $call->error_message)
                    <div class="mt-5 p-3 bg-red-50 rounded-xl text-sm text-red-700">
                        {{ $call->error_message }}
                    </div>
                @endif

                @if($call->status === 'failed')
                    <form method="POST" action="{{ route('calls.retry', $call) }}" class="mt-4">
                        @csrf
                        <button class="w-full py-2.5 bg-red-600 text-white text-sm font-semibold rounded-xl hover:bg-red-700 transition-colors">
                            Retry processing
                        </button>
                    </form>
                @else
                    <p class="mt-5 text-xs text-slate-400 text-center">Page refreshes automatically when complete…</p>
                @endif
            </div>
        </div>
    @endif

    {{-- ── Done state: split-pane results ────────────────────────────────────── --}}
    @if($call->status === 'done')
        <div x-data="resultsPage()"
             class="flex flex-col lg:flex-row h-[calc(100vh-3.5rem)] overflow-hidden">

            {{-- LEFT: Audio + Transcript --}}
            <div class="lg:w-5/12 flex flex-col border-b lg:border-b-0 lg:border-r border-slate-200
                        max-h-[50vh] lg:max-h-none">

                {{-- Score bar (mobile only — desktop shows in header) --}}
                <div class="lg:hidden flex items-center gap-3 px-4 py-2 bg-white border-b border-slate-100">
                    @php
                        $badgeColor = match($call->outcome) {
                            'pass'      => 'bg-emerald-100 text-emerald-700',
                            'auto_fail' => 'bg-red-100 text-red-700',
                            default     => 'bg-red-100 text-red-700',
                        };
                    @endphp
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $badgeColor }}">
                        {{ strtoupper(str_replace('_', '-', $call->outcome)) }}
                    </span>
                    <span class="text-2xl font-extrabold {{ $call->score_percent >= 80 ? 'text-emerald-600' : ($call->score_percent >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                        {{ number_format($call->score_percent, 0) }}%
                    </span>
                    @if($call->duration_seconds)
                        <span class="text-xs text-slate-400 ml-auto">{{ gmdate('i:s', $call->duration_seconds) }}</span>
                    @endif
                </div>

                {{-- Audio player --}}
                @if($call->audio_path)
                    <div class="px-4 py-3 bg-white border-b border-slate-100 shrink-0">
                        {{-- The browser's own ⋮ menu (download / speed) renders clipped here, so we hide it and provide our own controls below. --}}
                        <audio x-ref="audio" controls preload="metadata"
                               controlslist="nodownload noplaybackrate"
                               class="w-full h-8 accent-indigo-600"
                               @loadedmetadata="applySpeed()"
                               @timeupdate="trackTime()"
                               @seeked="trackTime(true)"
                               @play="trackTime(true)"
                               @ratechange="speed = $event.target.playbackRate">
                            <source src="{{ route('calls.audio', $call) }}" type="audio/mpeg">
                        </audio>
                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <label for="playback-speed" class="text-xs text-slate-500">Speed</label>
                            <select id="playback-speed"
                                    @change="setSpeed(parseFloat($event.target.value))"
                                    class="h-8 pl-2.5 pr-8 py-0 rounded-lg border-slate-200 bg-white text-xs font-medium text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                                <template x-for="rate in [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2]" :key="rate">
                                    <option :value="rate" :selected="speed === rate" x-text="rate === 1 ? 'Normal (1×)' : rate + '×'"></option>
                                </template>
                            </select>
                            <a href="{{ route('calls.audio', ['call' => $call, 'download' => 1]) }}"
                               class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                                Download
                            </a>
                            <label class="ml-auto flex items-center gap-1.5 text-xs text-slate-500 cursor-pointer">
                                <input type="checkbox" x-model="follow" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                Follow transcript
                            </label>
                        </div>
                    </div>
                @endif

                {{-- Transcript --}}
                <div class="flex-1 overflow-y-auto bg-slate-50">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-white border-b border-slate-100 sticky top-0">
                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">
                            Transcript <span class="text-slate-400 font-normal normal-case">({{ $call->segments->count() }} segments)</span>
                        </p>
                        <button @click="swapSpeakers()"
                                class="text-xs px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors">
                            ⇄ Swap speakers
                        </button>
                    </div>

                    <div class="px-4 py-3 space-y-3">
                        <template x-for="seg in segments" :key="seg.code">
                            <div :id="'seg-' + seg.code"
                                 @click="seekTo(seg.start_ms)"
                                 class="flex gap-2 cursor-pointer group rounded-xl transition-colors"
                                 :class="[seg.speaker === agentSpeaker ? 'flex-row' : 'flex-row-reverse',
                                          activeCode === seg.code ? 'bg-amber-100/70 ring-2 ring-amber-300 -mx-1.5 px-1.5 py-1' : '']">

                                {{-- Avatar --}}
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5"
                                     :class="seg.speaker === agentSpeaker ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-600'"
                                     x-text="speakerLabel(seg.speaker).charAt(0)"></div>

                                {{-- Bubble --}}
                                <div class="max-w-[80%]">
                                    <p class="text-xs font-semibold mb-0.5"
                                       :class="seg.speaker === agentSpeaker ? 'text-indigo-600' : 'text-slate-500'"
                                       x-text="speakerLabel(seg.speaker) + ' · ' + formatTime(seg.start_ms)"></p>
                                    <div class="rounded-2xl px-3.5 py-2.5 text-sm leading-snug group-hover:opacity-90 transition-opacity"
                                         :class="seg.speaker === agentSpeaker
                                             ? 'bg-indigo-600 text-white rounded-tl-sm'
                                             : 'bg-white border border-slate-200 text-slate-700 rounded-tr-sm'">
                                        <span class="font-mono text-xs opacity-50 mr-1" x-text="seg.code"></span>
                                        <span x-text="seg.text"></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        @if($call->segments->isEmpty())
                            <div class="text-center py-8 text-slate-400 text-sm">No transcript available.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- RIGHT: Criteria panel --}}
            <div class="lg:w-7/12 flex flex-col overflow-hidden">

                {{-- Desktop score bar --}}
                <div class="hidden lg:flex items-center gap-4 px-5 py-3 bg-white border-b border-slate-100 shrink-0">
                    @php
                        $outcomeLabel = strtoupper(str_replace('_', '-', $call->outcome));
                    @endphp
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wide mb-0.5">Overall Score</p>
                        <p class="text-2xl font-extrabold {{ $call->score_percent >= 80 ? 'text-emerald-600' : ($call->score_percent >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                            {{ number_format($call->score_percent, 1) }}%
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $badgeColor }}">{{ $outcomeLabel }}</span>
                    @if($call->duration_seconds)
                        <div class="ml-auto text-right">
                            <p class="text-xs text-slate-400">Duration</p>
                            <p class="text-sm font-semibold text-slate-700">{{ gmdate('i:s', $call->duration_seconds) }}</p>
                        </div>
                    @endif
                </div>

                {{-- Criteria list --}}
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3"
                     x-data="{ reviewOnly: false }">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Criteria</h3>
                        <div class="flex items-center gap-3">
                            @php $needsReviewCount = $call->criterionResults->where('needs_review', true)->count(); @endphp
                            @if($needsReviewCount > 0)
                                <label class="flex items-center gap-1.5 text-xs text-slate-500 cursor-pointer select-none">
                                    <input type="checkbox" x-model="reviewOnly"
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>Needs review only
                                        <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded-full ml-0.5">{{ $needsReviewCount }}</span>
                                    </span>
                                </label>
                            @endif
                            <span class="text-xs text-slate-400">{{ $call->criterionResults->count() }} criteria</span>
                        </div>
                    </div>

                    @forelse($call->criterionResults as $result)
                        @php
                            $effective = $result->effectiveResult();
                            $isExtract = $result->criterion_type === 'extract';
                            $isGood    = in_array($effective, ['pass', 'yes']) || (is_numeric($effective) && (int)$effective >= 4);
                            $isBad     = in_array($effective, ['fail', 'no'])  || (is_numeric($effective) && (int)$effective <= 2);
                            $hasErrors = $errors->hasBag("cr_{$result->id}");
                        @endphp

                        <div x-show="!reviewOnly || {{ $result->needs_review ? 'true' : 'false' }}"
                             class="bg-white rounded-2xl border shadow-sm overflow-hidden
                                    {{ $result->needs_review ? 'border-amber-300' : 'border-slate-100' }}">

                            {{-- Card header --}}
                            <div class="flex items-start gap-3 p-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                        <h4 class="text-sm font-semibold text-slate-800">{{ $result->criterion_name }}</h4>
                                        @if($result->criterion_is_critical)
                                            <span class="px-1.5 py-0.5 bg-red-50 text-red-600 text-xs font-semibold rounded-md border border-red-200">
                                                CRITICAL
                                            </span>
                                        @endif
                                        @if($result->needs_review)
                                            <span class="px-1.5 py-0.5 bg-amber-50 text-amber-600 text-xs font-medium rounded-md border border-amber-200">
                                                ⚑ Review
                                            </span>
                                        @endif
                                        @if($result->overrides->isNotEmpty())
                                            <span class="px-1.5 py-0.5 bg-indigo-50 text-indigo-600 text-xs font-medium rounded-md">
                                                ✎ Overridden
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-400">
                                        {{ ucfirst(str_replace('_', ' ', $result->criterion_type)) }}
                                        · weight {{ $result->criterion_weight }}
                                        · {{ ucfirst($result->verification_status) }}
                                    </p>
                                </div>

                                {{-- Result badge --}}
                                <div class="shrink-0 text-right">
                                    @if($isExtract)
                                        <p class="text-sm text-slate-700 italic max-w-[140px] truncate">{{ $effective ?: '—' }}</p>
                                    @else
                                        @if($result->overrides->isNotEmpty())
                                            <span class="text-xs line-through text-slate-400 mr-1">{{ strtoupper($result->ai_result) }}</span>
                                        @endif
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-sm font-bold
                                            {{ $isGood ? 'bg-emerald-100 text-emerald-700' : '' }}
                                            {{ $isBad  ? 'bg-red-100 text-red-700'         : '' }}
                                            {{ !$isGood && !$isBad ? 'bg-slate-100 text-slate-600' : '' }}">
                                            {{ strtoupper($effective) }}
                                        </span>
                                    @endif
                                    @if(!$isExtract)
                                        <div class="mt-1.5">
                                            <p class="text-xs text-slate-400 mb-0.5">{{ round($result->confidence * 100) }}% confidence</p>
                                            <div class="h-1.5 bg-slate-100 rounded-full w-20 ml-auto">
                                                <div class="h-1.5 rounded-full
                                                    {{ $result->confidence >= 0.8 ? 'bg-emerald-400' : ($result->confidence >= 0.6 ? 'bg-amber-400' : 'bg-red-400') }}"
                                                     style="width:{{ round($result->confidence * 100) }}%"></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Reasoning --}}
                            @if($result->reasoning)
                                <div class="px-4 pb-3">
                                    <p class="text-xs text-slate-500 italic leading-relaxed bg-slate-50 rounded-xl p-2.5">
                                        {{ $result->reasoning }}
                                    </p>
                                </div>
                            @endif

                            {{-- Evidence --}}
                            @if(!empty($result->evidence))
                                <div class="px-4 pb-3 space-y-1.5">
                                    @foreach($result->evidence as $ev)
                                        <div class="flex items-start gap-2 text-xs">
                                            <span class="shrink-0 font-mono px-1.5 py-0.5 rounded-md text-xs font-semibold
                                                {{ ($ev['verified'] ?? false)
                                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                    : 'bg-red-50 text-red-500 border border-red-200' }}">
                                                {{ $ev['segment_code'] }}
                                            </span>
                                            <button @click="seekToCode('{{ $ev['segment_code'] }}')"
                                                    class="text-left text-slate-500 hover:text-indigo-600 italic transition-colors leading-snug text-xs">
                                                "{{ Str::limit($ev['quote'] ?? '', 120) }}"
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Override section --}}
                            <div x-data="{ open: {{ $hasErrors ? 'true' : 'false' }} }"
                                 class="border-t border-slate-100">
                                <div class="flex items-center justify-between px-4 py-2.5">
                                    @if($result->overrides->isNotEmpty())
                                        <span class="text-xs text-indigo-600 font-medium">✎ Human reviewed</span>
                                    @else
                                        <span></span>
                                    @endif
                                    <div class="flex items-center gap-3">
                                        @if($result->needs_review && $result->overrides->isEmpty())
                                            <form method="POST" action="{{ route('overrides.store', $result) }}">
                                                @csrf
                                                <input type="hidden" name="new_result" value="{{ $result->ai_result }}">
                                                <input type="hidden" name="reason" value="Reviewed: AI result confirmed correct.">
                                                <button type="submit"
                                                        class="text-xs text-emerald-600 hover:text-emerald-800 transition-colors">
                                                    ✓ Mark AI correct
                                                </button>
                                            </form>
                                        @endif
                                        <button @click="open = !open" type="button"
                                                class="text-xs text-slate-400 hover:text-slate-700 transition-colors"
                                                x-text="open ? 'Cancel' : 'Override result'"></button>
                                    </div>
                                </div>

                                {{-- Override form --}}
                                <div x-show="open" x-cloak class="px-4 pb-4">
                                    <form method="POST" action="{{ route('overrides.store', $result) }}">
                                        @csrf
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            @if($result->criterion_type === 'pass_fail')
                                                <select name="new_result"
                                                        class="border-slate-200 rounded-xl text-sm bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500 shrink-0">
                                                    <option value="">— select —</option>
                                                    <option value="pass">Pass</option>
                                                    <option value="fail">Fail</option>
                                                </select>
                                            @elseif($result->criterion_type === 'yes_no_na')
                                                <select name="new_result"
                                                        class="border-slate-200 rounded-xl text-sm bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500 shrink-0">
                                                    <option value="">— select —</option>
                                                    <option value="yes">Yes</option>
                                                    <option value="no">No</option>
                                                    <option value="na">N/A</option>
                                                </select>
                                            @elseif($result->criterion_type === 'scale_1_5')
                                                <select name="new_result"
                                                        class="border-slate-200 rounded-xl text-sm bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500 shrink-0">
                                                    <option value="">— select —</option>
                                                    @foreach(range(1, 5) as $n)
                                                        <option value="{{ $n }}">{{ $n }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="text" name="new_result"
                                                       placeholder="Corrected value"
                                                       class="border-slate-200 rounded-xl text-sm bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500 flex-1">
                                            @endif
                                            <textarea name="reason" rows="2" required minlength="10"
                                                      placeholder="Reason for override (required, min 10 chars)"
                                                      class="border-slate-200 rounded-xl text-sm bg-slate-50 focus:ring-indigo-500 focus:border-indigo-500 flex-1 resize-none"></textarea>
                                            <button type="submit"
                                                    class="shrink-0 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 transition-colors self-start">
                                                Save
                                            </button>
                                        </div>
                                        @error('new_result', "cr_{$result->id}")
                                            <p class="text-red-600 text-xs mt-1.5">{{ $message }}</p>
                                        @enderror
                                        @error('reason', "cr_{$result->id}")
                                            <p class="text-red-600 text-xs mt-1.5">{{ $message }}</p>
                                        @enderror
                                    </form>
                                </div>

                                {{-- Audit trail --}}
                                @if($result->overrides->isNotEmpty())
                                    <div class="px-4 pb-4 space-y-2">
                                        @foreach($result->overrides as $ov)
                                            <div class="bg-indigo-50 rounded-xl p-3 border border-indigo-100 text-xs">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="font-semibold text-slate-800">{{ $ov->user->name }}</span>
                                                    <span class="text-slate-400">changed</span>
                                                    <span class="font-mono bg-red-50 text-red-700 px-1.5 py-0.5 rounded">{{ strtoupper($ov->old_result) }}</span>
                                                    <span class="text-slate-400">→</span>
                                                    <span class="font-mono bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded font-semibold">{{ strtoupper($ov->new_result) }}</span>
                                                    <span class="text-slate-400 ml-auto">{{ $ov->created_at->diffForHumans() }}</span>
                                                </div>
                                                <p class="mt-1.5 text-slate-500 italic">"{{ $ov->reason }}"</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-slate-400 text-sm">
                            No criterion results yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <script>
    function callStatus(initial, statusUrl) {
        return {
            status: initial,
            errorMsg: null,
            interval: null,
            init() {
                if (['queued','normalising','transcribing','scoring'].includes(this.status)) {
                    this.interval = setInterval(() => this.poll(), 3000);
                }
            },
            async poll() {
                try {
                    const r = await fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await r.json();
                    this.status   = data.status;
                    this.errorMsg = data.error;
                    if (data.status === 'done') { clearInterval(this.interval); window.location.reload(); }
                    else if (data.status === 'failed') { clearInterval(this.interval); window.location.reload(); }
                } catch(e) {}
            },
            statusLabel() {
                return {
                    queued:       'Queued — waiting for a worker…',
                    normalising:  'Normalising audio with ffmpeg…',
                    transcribing: 'Transcribing with Deepgram…',
                    scoring:      'Scoring with AI…',
                    failed:       'Processing failed.',
                }[this.status] ?? this.status;
            },
        };
    }

    function resultsPage() {
        return {
            agentSpeaker: @json($call->agent_speaker ?? 0),
            segments: @json($segmentsJson),
            swapUrl: @json(route('calls.swap_speaker', $call)),
            speed: 1,
            follow: true,
            activeCode: null,

            // Always open at normal speed; the browser can otherwise carry over a previous rate.
            applySpeed() {
                const audio = this.$refs.audio;
                if (audio) { audio.defaultPlaybackRate = this.speed; audio.playbackRate = this.speed; }
            },

            setSpeed(rate) {
                this.speed = rate;
                this.applySpeed();
            },

            // Highlight the line being spoken and keep it in view. After a seek (dragging or
            // clicking the progress bar) or pressing play, jump to that line straight away.
            trackTime(jump = false) {
                const ms = this.$refs.audio.currentTime * 1000;
                let current = this.segments[0] ?? null;
                for (const seg of this.segments) {
                    if (seg.start_ms > ms) break;
                    current = seg;
                }
                const code = current?.code ?? null;
                if (code === this.activeCode && !jump) return;
                this.activeCode = code;
                if (code && this.follow && (jump || !this.$refs.audio.paused)) {
                    document.getElementById('seg-' + code)?.scrollIntoView({ behavior: 'smooth', block: jump ? 'center' : 'nearest' });
                }
            },

            seekTo(startMs) {
                const audio = this.$refs.audio;
                if (audio) { audio.currentTime = startMs / 1000; audio.play().catch(() => {}); }
            },

            seekToCode(code) {
                const seg = this.segments.find(s => s.code === code);
                if (!seg) return;
                this.seekTo(seg.start_ms);
                const el = document.getElementById('seg-' + code);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            },

            speakerLabel(speaker) {
                return speaker === this.agentSpeaker ? 'AGENT' : 'CUSTOMER';
            },

            formatTime(ms) {
                const s = Math.floor(ms / 1000);
                return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
            },

            async swapSpeakers() {
                const r = await fetch(this.swapUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                if (r.ok) { this.agentSpeaker = this.agentSpeaker === 0 ? 1 : 0; }
            },
        };
    }
    </script>
</x-app-layout>
