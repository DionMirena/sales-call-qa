<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        $templates = Template::withCount('criteria')
            ->with('creator')
            ->latest()
            ->paginate(20);

        return view('templates.index', compact('templates'));
    }

    public function create(): View
    {
        return view('templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'pass_threshold' => 'required|integer|min:1|max:100',
            'criteria'       => 'required|array|min:1',
            'criteria.*.name'           => 'required|string|max:255',
            'criteria.*.description'    => 'required|string',
            'criteria.*.fail_conditions'=> 'nullable|string',
            'criteria.*.type'           => 'required|in:pass_fail,scale_1_5,yes_no_na,extract',
            'criteria.*.weight'         => 'required|integer|min:0|max:10',
            'criteria.*.is_critical'    => 'boolean',
            'criteria.*.phase_hint'     => 'required|in:opening,discovery,pitch,objections,close,anywhere',
        ]);

        $template = Template::create([
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'pass_threshold' => $data['pass_threshold'],
            'created_by'     => $request->user()->id,
        ]);

        $this->syncCriteria($template, $data['criteria']);

        return redirect()->route('templates.show', $template)
            ->with('success', 'Template created.');
    }

    public function show(Template $template): View
    {
        $template->load(['criteria', 'creator']);

        return view('templates.show', compact('template'));
    }

    public function edit(Template $template): View
    {
        $this->authorize('update', $template);
        $template->load('criteria');

        return view('templates.edit', compact('template'));
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'pass_threshold' => 'required|integer|min:1|max:100',
            'criteria'       => 'required|array|min:1',
            'criteria.*.name'           => 'required|string|max:255',
            'criteria.*.description'    => 'required|string',
            'criteria.*.fail_conditions'=> 'nullable|string',
            'criteria.*.type'           => 'required|in:pass_fail,scale_1_5,yes_no_na,extract',
            'criteria.*.weight'         => 'required|integer|min:0|max:10',
            'criteria.*.is_critical'    => 'boolean',
            'criteria.*.phase_hint'     => 'required|in:opening,discovery,pitch,objections,close,anywhere',
        ]);

        $template->update([
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'pass_threshold' => $data['pass_threshold'],
        ]);

        $template->criteria()->delete();
        $this->syncCriteria($template, $data['criteria']);

        return redirect()->route('templates.show', $template)
            ->with('success', 'Template updated.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        $this->authorize('delete', $template);
        $template->delete();

        return redirect()->route('templates.index')
            ->with('success', 'Template deleted.');
    }

    public function duplicate(Template $template): RedirectResponse
    {
        $this->authorize('create', Template::class);

        $copy = $template->replicate(['created_by']);
        $copy->name       = $template->name . ' (copy)';
        $copy->created_by = request()->user()->id;
        $copy->save();

        foreach ($template->criteria as $criterion) {
            $copy->criteria()->create($criterion->only([
                'position', 'name', 'description', 'fail_conditions',
                'type', 'weight', 'is_critical', 'phase_hint',
            ]));
        }

        return redirect()->route('templates.edit', $copy)
            ->with('success', 'Template duplicated.');
    }

    private function syncCriteria(Template $template, array $rows): void
    {
        foreach ($rows as $i => $row) {
            $template->criteria()->create([
                'position'       => $i,
                'name'           => $row['name'],
                'description'    => $row['description'],
                'fail_conditions'=> $row['fail_conditions'] ?? null,
                'type'           => $row['type'],
                'weight'         => (int) $row['weight'],
                'is_critical'    => (bool) ($row['is_critical'] ?? false),
                'phase_hint'     => $row['phase_hint'],
            ]);
        }
    }
}
