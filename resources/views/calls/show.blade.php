<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">
                Call: {{ $call->original_filename }}
            </h2>
            <span class="text-sm text-gray-500">
                Agent: {{ $call->agent->name }}
                &middot; Template: {{ $call->template->name }}
            </span>
        </div>
    </x-slot>

    <div class="py-6 max-w-4xl mx-auto px-4"
         x-data="callStatus('{{ $call->status }}', '{{ route('calls.status', $call) }}')"
         x-init="init()">

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('success') }}</div>
        @endif

        {{-- Status banner --}}
        <div class="mb-6 p-4 rounded-lg border"
             :class="{
                 'bg-yellow-50 border-yellow-200': ['queued','normalising','transcribing','scoring'].includes(status),
                 'bg-green-50 border-green-200': status === 'done',
                 'bg-red-50 border-red-200': status === 'failed',
             }">

            <div class="flex items-center gap-3">
                {{-- Spinner --}}
                <template x-if="['queued','normalising','transcribing','scoring'].includes(status)">
                    <svg class="animate-spin h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                </template>

                <div>
                    <p class="font-medium capitalize"
                       :class="{
                           'text-yellow-800': ['queued','normalising','transcribing','scoring'].includes(status),
                           'text-green-800': status === 'done',
                           'text-red-800': status === 'failed',
                       }"
                       x-text="statusLabel()"></p>

                    <template x-if="status === 'failed' && errorMsg">
                        <p class="text-sm text-red-600 mt-1" x-text="errorMsg"></p>
                    </template>
                </div>
            </div>

            {{-- Retry button --}}
            <template x-if="status === 'failed'">
                <form method="POST" action="{{ route('calls.retry', $call) }}" class="mt-3">
                    @csrf
                    <button class="px-4 py-1.5 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                        Retry processing
                    </button>
                </form>
            </template>

            {{-- Results link --}}
            <template x-if="status === 'done'">
                <a href="{{ route('calls.show', $call) }}"
                   class="mt-3 inline-block px-4 py-1.5 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                    View results ↓
                </a>
            </template>
        </div>

        {{-- Results (only shown when done) --}}
        @if($call->status === 'done')
            <div class="bg-white shadow rounded p-5 mb-4">
                <dl class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Score</dt>
                        <dd class="text-2xl font-bold">{{ number_format($call->score_percent, 1) }}%</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Outcome</dt>
                        <dd class="mt-1">
                            @if($call->outcome === 'pass')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded font-semibold">PASS</span>
                            @elseif($call->outcome === 'auto_fail')
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded font-semibold">AUTO-FAIL</span>
                            @else
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded font-semibold">FAIL</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Duration</dt>
                        <dd class="font-medium">
                            {{ $call->duration_seconds ? gmdate('i:s', $call->duration_seconds) : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            @foreach($call->criterionResults as $result)
                <div class="bg-white shadow rounded p-4 mb-3 {{ $result->needs_review ? 'border-l-4 border-yellow-400' : '' }}">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="font-medium text-gray-800">{{ $result->criterion_name }}</h4>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $result->criterion_type }}</p>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-lg">{{ $result->effectiveResult() }}</span>
                            <p class="text-xs text-gray-400">{{ $result->verification_status }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">{{ $result->reasoning }}</p>
                    @if($result->needs_review)
                        <span class="mt-2 inline-block text-xs px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded">
                            ⚑ Needs review
                        </span>
                    @endif
                </div>
            @endforeach
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
                    if (data.status === 'done') {
                        clearInterval(this.interval);
                        window.location.reload();
                    } else if (data.status === 'failed') {
                        clearInterval(this.interval);
                    }
                } catch (e) { /* network error — keep polling */ }
            },
            statusLabel() {
                const labels = {
                    queued:       'Queued — waiting for a worker…',
                    normalising:  'Normalising audio with ffmpeg…',
                    transcribing: 'Transcribing with Deepgram…',
                    scoring:      'Scoring with AI…',
                    done:         'Done! Scroll down for results.',
                    failed:       'Processing failed.',
                };
                return labels[this.status] ?? this.status;
            },
        };
    }
    </script>
</x-app-layout>
