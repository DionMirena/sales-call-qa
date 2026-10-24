<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCallJob;
use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CallController extends Controller
{
    private const ALLOWED_MIMES = [
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/x-m4a',
        'audio/aac',
        'audio/ogg',
        'audio/flac',
        'audio/x-flac',
        'audio/webm',
        'video/webm',
        'video/mp4',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Call::class);

        $isAdmin = auth()->user()->isAdmin();

        $query = Call::with(['agent', 'template'])
            ->when(! $isAdmin, fn ($q) => $q->where('uploaded_by', auth()->id()));

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }
        if ($request->filled('template_id')) {
            $query->where('template_id', $request->template_id);
        }
        if ($request->filled('outcome')) {
            $query->where('outcome', $request->outcome);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('score_min')) {
            $query->where('score_percent', '>=', (float) $request->score_min);
        }
        if ($request->filled('score_max')) {
            $query->where('score_percent', '<=', (float) $request->score_max);
        }
        if ($request->boolean('needs_review')) {
            $query->whereHas('criterionResults', fn ($q) => $q->where('needs_review', true));
        }

        // Sort options shown in the list's "Sort" dropdown: key => [column, direction].
        $sorts = [
            'newest'      => ['created_at', 'desc'],
            'oldest'      => ['created_at', 'asc'],
            'number_asc'  => ['id', 'asc'],
            'number_desc' => ['id', 'desc'],
            'longest'     => ['duration_seconds', 'desc'],
            'shortest'    => ['duration_seconds', 'asc'],
        ];
        [$column, $direction] = $sorts[$request->input('sort')] ?? $sorts['newest'];

        $calls     = $query->orderBy($column, $direction)->orderBy('id', $direction)->paginate(25)->withQueryString();
        $agents    = Agent::orderBy('name')->get(['id', 'name']);
        $templates = Template::orderBy('name')->get(['id', 'name']);

        return view('calls.index', compact('calls', 'agents', 'templates'));
    }

    public function create(): View
    {
        $templates = Template::orderBy('name')->get(['id', 'name']);
        $agents    = Agent::orderBy('name')->get(['id', 'name', 'team']);

        return view('calls.create', compact('templates', 'agents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'template_id'   => 'required|exists:templates,id',
            'agent_id'      => 'required|exists:agents,id',
            'customer_name' => 'nullable|string|max:255',
            'policy_type'   => 'nullable|string|max:255',
            'audio'         => [
                'required',
                'file',
                'max:1048576', // 1 GB in KB
                function ($attribute, $value, $fail) {
                    if (! in_array($value->getMimeType(), self::ALLOWED_MIMES)) {
                        $fail('Unsupported audio format. Accepted: mp3, wav, m4a, aac, ogg, flac, webm, mp4.');
                    }
                },
            ],
        ]);

        $file         = $request->file('audio');
        $originalPath = $file->store('calls/originals', 'private');

        $call = Call::create([
            'agent_id'          => $request->agent_id,
            'template_id'       => $request->template_id,
            'uploaded_by'       => $request->user()->id,
            'customer_name'     => $request->customer_name,
            'policy_type'       => $request->policy_type,
            'original_path'     => $originalPath,
            'original_filename' => $file->getClientOriginalName(),
            'status'            => 'queued',
        ]);

        ProcessCallJob::dispatch($call->id);

        return redirect()->route('calls.show', $call)
            ->with('success', 'Call uploaded and queued for processing.');
    }

    public function show(Call $call): View
    {
        $this->authorize('view', $call);
        $call->load(['agent', 'template', 'uploader', 'criterionResults.overrides.user', 'segments']);

        $segmentsJson = $call->segments->map(fn ($s) => [
            'code'     => $s->code,
            'speaker'  => $s->speaker,
            'start_ms' => $s->start_ms,
            'end_ms'   => $s->end_ms,
            'text'     => $s->text,
        ])->values()->all();

        return view('calls.show', compact('call', 'segmentsJson'));
    }

    public function swapSpeaker(Call $call): JsonResponse
    {
        $this->authorize('view', $call);
        abort_unless($call->status === 'done', 422, 'Can only swap speakers on completed calls.');

        $call->update(['agent_speaker' => 1 - ($call->agent_speaker ?? 0)]);

        return response()->json(['agent_speaker' => $call->agent_speaker]);
    }

    public function audio(Call $call): BinaryFileResponse
    {
        $this->authorize('view', $call);

        $path = Storage::disk('private')->path($call->audio_path);

        abort_unless(file_exists($path), 404);

        if (request()->boolean('download')) {
            $name = pathinfo($call->original_filename, PATHINFO_FILENAME) . '.mp3';

            return response()->download($path, $name, ['Content-Type' => 'audio/mpeg']);
        }

        return response()->file($path, [
            'Content-Type'  => 'audio/mpeg',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function status(Call $call): JsonResponse
    {
        $this->authorize('view', $call);

        return response()->json([
            'status'  => $call->status,
            'outcome' => $call->outcome,
            'error'   => $call->error_message,
        ]);
    }

    public function destroy(Call $call): RedirectResponse
    {
        $this->authorize('delete', $call);

        foreach (array_filter([$call->original_path, $call->audio_path]) as $path) {
            Storage::disk('private')->delete($path);
        }

        $call->delete();

        return redirect()->route('calls.index')
            ->with('success', 'Call and all associated data deleted.');
    }

    public function rescore(Request $request, Call $call): RedirectResponse
    {
        $this->authorize('view', $call);
        abort_unless($call->status === 'done', 422, 'Only completed calls can be re-scored.');

        if ($request->filled('template_id')) {
            $request->validate(['template_id' => 'exists:templates,id']);
            $call->template_id = $request->template_id;
            $call->save();
        }

        // Keep segments (transcript); only clear previous scores and overrides.
        $call->criterionResults()->delete();

        $call->update([
            'status'        => 'queued',
            'score_percent' => null,
            'outcome'       => null,
            'error_message' => null,
        ]);

        ProcessCallJob::dispatch($call->id, rescoreOnly: true);

        return redirect()->route('calls.show', $call)
            ->with('success', 'Call queued for re-scoring.');
    }

    public function retry(Call $call): RedirectResponse
    {
        $this->authorize('view', $call);

        abort_unless($call->status === 'failed', 422, 'Only failed calls can be retried.');

        $call->update([
            'status'        => 'queued',
            'error_message' => null,
        ]);

        $call->segments()->delete();
        $call->criterionResults()->delete();

        ProcessCallJob::dispatch($call->id);

        return redirect()->route('calls.show', $call)
            ->with('success', 'Call re-queued for processing.');
    }
}
