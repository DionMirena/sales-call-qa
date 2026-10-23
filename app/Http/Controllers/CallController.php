<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCallJob;
use App\Models\Agent;
use App\Models\Call;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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
        $call->load(['agent', 'template', 'uploader', 'criterionResults']);

        return view('calls.show', compact('call'));
    }

    public function audio(Call $call): Response
    {
        $this->authorize('view', $call);

        $path = Storage::disk('private')->path($call->audio_path);

        abort_unless(file_exists($path), 404);

        return response()->file($path, [
            'Content-Type'  => 'audio/mpeg',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function status(Call $call): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $call);

        return response()->json([
            'status'  => $call->status,
            'outcome' => $call->outcome,
            'error'   => $call->error_message,
        ]);
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
