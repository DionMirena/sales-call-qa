<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold text-gray-800 truncate">
                    {{ $call->original_filename }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Agent: <strong>{{ $call->agent->name }}</strong>
                    &middot; {{ $call->template->name }}
                    @if($call->customer_name)
                        &middot; {{ $call->customer_name }}
                    @endif
                </p>
            </div>
            @if($call->status === 'done')
                @php
                    $badgeClass = match($call->outcome) {
                        'pass'      => 'bg-green-100 text-green-800',
                        'auto_fail' => 'bg-red-100 text-red-800',
                        default     => 'bg-yellow-100 text-yellow-800',
                    };
                @endphp
                <span class="shrink-0 px-3 py-1 rounded-full font-bold text-sm {{ $badgeClass }}">
                    {{ strtoupper(str_replace('_', '-', $call->outcome)) }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-6 max-w-5xl mx-auto px-4 space-y-5">

        @if(session('success'))
            <div class="p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('success') }}</div>
        @endif

        {{-- ── Status banner (queued / processing / failed) ──────────────────────── --}}
        @if($call->status !== 'done')
            <div x-data="callStatus('{{ $call->status }}', '{{ route('calls.status', $call) }}')"
                 x-init="init()"
                 class="rounded-lg border p-4"
                 :class="{
                     'bg-yellow-50 border-yellow-200': ['queued','normalising','transcribing','scoring'].includes(status),
                     'bg-red-50   border-red-200':     status === 'failed',
                 }">

                <div class="flex items-center gap-3">
                    <template x-if="['queued','normalising','transcribing','scoring'].includes(status)">
                        <svg class="animate-spin h-5 w-5 text-yellow-600 shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                    </template>
                    <div>
                        <p class="font-medium"
                           :class="{
                               'text-yellow-800': ['queued','normalising','transcribing','scoring'].includes(status),
                               'text-red-800':    status === 'failed',
                           }"
                           x-text="statusLabel()"></p>
                        <template x-if="status === 'failed' && errorMsg">
                            <p class="text-sm text-red-600 mt-1" x-text="errorMsg"></p>
                        </template>
                    </div>
                </div>

                <template x-if="status === 'failed'">
                    <form method="POST" action="{{ route('calls.retry', $call) }}" class="mt-3">
                        @csrf
                        <button class="px-4 py-1.5 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                            Retry processing
                        </button>
                    </form>
                </template>
            </div>
        @endif

        {{-- ── Full results (done state) ───────────────────────────────────────────── --}}
        @if($call->status === 'done')
            <div x-data="resultsPage()" x-init="init()">

                {{-- Score summary ──────────────────────────────────────────────────── --}}
                <div class="bg-white shadow-sm rounded-lg p-5 grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Score</p>
                        <p class="text-3xl font-extrabold text-gray-900">{{ number_format($call->score_percent, 1) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Outcome</p>
                        @if($call->outcome === 'pass')
                            <span class="px-2.5 py-1 bg-green-100 text-green-800 rounded-full font-bold text-sm">PASS</span>
                        @elseif($call->outcome === 'auto_fail')
                            <span class="px-2.5 py-1 bg-red-100 text-red-800 rounded-full font-bold text-sm">AUTO-FAIL</span>
                        @else
                            <span class="px-2.5 py-1 bg-yellow-100 text-yellow-800 rounded-full font-bold text-sm">FAIL</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Duration</p>
                        <p class="font-semibold">{{ $call->duration_seconds ? gmdate('i:s', $call->duration_seconds) : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Agent</p>
                        <p class="font-semibold truncate">{{ $call->agent->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Template</p>
                        <p class="font-semibold truncate text-xs">{{ $call->template->name }}</p>
                    </div>
                </div>

                {{-- Audio player ────────────────────────────────────────────────────── --}}
                @if($call->audio_path)
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Audio</p>
                        <audio x-ref="audio" controls preload="metadata" class="w-full">
                            <source src="{{ route('calls.audio', $call) }}" type="audio/mpeg">
                        </audio>
                    </div>
                @endif

                {{-- Transcript ──────────────────────────────────────────────────────── --}}
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b bg-gray-50">
                        <p class="font-semibold text-gray-700 text-sm">
                            Transcript
                            <span class="text-gray-400 font-normal ml-1">({{ $call->segments->count() }} segments)</span>
                        </p>
                        <button @click="swapSpeakers()"
                                class="text-xs px-3 py-1.5 rounded-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">
                            ⇄ Swap speakers
                        </button>
                    </div>

                    <div class="overflow-y-auto divide-y divide-gray-100" style="max-height:420px">
                        <template x-for="seg in segments" :key="seg.code">
                            <div class="flex gap-3 px-4 py-2.5 text-sm cursor-pointer hover:bg-blue-50/60 transition-colors"
                                 :id="'seg-' + seg.code"
                                 @click="seekTo(seg.start_ms)">
                                <div class="shrink-0 w-28 text-xs pt-0.5 space-y-0.5">
                                    <p class="font-semibold font-mono"
                                       :class="seg.speaker === agentSpeaker ? 'text-blue-700' : 'text-gray-500'"
                                       x-text="speakerLabel(seg.speaker)"></p>
                                    <p class="text-gray-400" x-text="formatTime(seg.start_ms)"></p>
                                </div>
                                <div class="flex-1 leading-snug">
                                    <span class="text-gray-300 font-mono text-xs select-none mr-1" x-text="seg.code"></span>
                                    <span class="text-gray-700" x-text="seg.text"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Criterion result cards ───────────────────────────────────────────── --}}
                <div class="space-y-3">
                    <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Criterion Results</h3>

                    @foreach($call->criterionResults as $result)
                        @php
                            $effective    = $result->effectiveResult();
                            $isExtract    = $result->criterion_type === 'extract';
                            $isGood       = in_array($effective, ['pass', 'yes'])
                                            || (is_numeric($effective) && (int)$effective >= 4);
                            $isBad        = in_array($effective, ['fail', 'no'])
                                            || (is_numeric($effective) && (int)$effective <= 2);
                        @endphp
                        <div class="bg-white shadow-sm rounded-lg p-4
                            {{ $result->needs_review ? 'border-l-4 border-l-yellow-400' : 'border border-gray-100' }}">

                            {{-- Card header --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                        <h4 class="font-semibold text-gray-800 text-sm">{{ $result->criterion_name }}</h4>
                                        @if($result->criterion_is_critical)
                                            <span class="text-xs px-1.5 py-0.5 bg-red-50 text-red-700 rounded border border-red-200 font-medium">CRITICAL</span>
                                        @endif
                                        @if($result->needs_review)
                                            <span class="text-xs px-1.5 py-0.5 bg-yellow-50 text-yellow-700 rounded border border-yellow-200 font-medium">⚑ Needs review</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-400">
                                        {{ ucfirst(str_replace('_', ' ', $result->criterion_type)) }}
                                        &middot; weight {{ $result->criterion_weight }}
                                        &middot; {{ ucfirst($result->verification_status) }}
                                    </p>
                                </div>

                                {{-- Result badge + confidence --}}
                                <div class="shrink-0 text-right">
                                    @if($isExtract)
                                        <p class="text-sm text-gray-700 italic">{{ $effective ?: '—' }}</p>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-sm font-bold
                                            {{ $isGood ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $isBad  ? 'bg-red-100   text-red-800'   : '' }}
                                            {{ !$isGood && !$isBad ? 'bg-gray-100 text-gray-600' : '' }}
                                        ">{{ strtoupper($effective) }}</span>
                                    @endif

                                    @if(!$isExtract)
                                        <div class="mt-1.5 text-xs text-gray-400">
                                            {{ round($result->confidence * 100) }}% confidence
                                            <div class="h-1.5 bg-gray-100 rounded-full mt-0.5 w-20 ml-auto">
                                                <div class="h-1.5 rounded-full
                                                    {{ $result->confidence >= 0.8 ? 'bg-green-400' : ($result->confidence >= 0.6 ? 'bg-yellow-400' : 'bg-red-400') }}"
                                                     style="width:{{ round($result->confidence * 100) }}%"></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Reasoning --}}
                            @if($result->reasoning)
                                <p class="text-sm text-gray-500 mt-2 italic leading-snug">{{ $result->reasoning }}</p>
                            @endif

                            {{-- Evidence --}}
                            @if(!empty($result->evidence))
                                <div class="mt-3 space-y-1.5">
                                    @foreach($result->evidence as $ev)
                                        <div class="flex items-start gap-2 text-xs">
                                            <span class="shrink-0 font-mono px-1.5 py-0.5 rounded
                                                {{ ($ev['verified'] ?? false) ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-500 border border-red-200' }}">
                                                {{ $ev['segment_code'] }}
                                            </span>
                                            <button @click="seekToCode('{{ $ev['segment_code'] }}')"
                                                    title="Seek audio to this segment"
                                                    class="text-left text-gray-500 hover:text-blue-600 italic transition-colors leading-snug">
                                                "{{ Str::limit($ev['quote'] ?? '', 120) }}"
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

            </div>{{-- /x-data resultsPage --}}
        @endif

    </div>

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
                    else if (data.status === 'failed') { clearInterval(this.interval); }
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

            init() {},

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
