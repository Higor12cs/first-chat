<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiObjectiveRequest;
use App\Http\Resources\AiObjectiveResource;
use App\Http\Resources\ServiceQueueResource;
use App\Models\AiObjective;
use App\Models\ServiceQueue;
use App\Services\Ai\AiManager;
use App\Services\Ai\ToolRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AiObjectiveController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('AiObjectives/Index', [
            'objectives' => AiObjectiveResource::collection(
                AiObjective::query()->withCount('interactions')->orderBy('name')->get()
            ),
        ]);
    }

    public function create(AiManager $ai, ToolRegistry $tools): Response
    {
        return Inertia::render('AiObjectives/Form', $this->formData($ai, $tools));
    }

    public function edit(AiObjective $aiObjective, AiManager $ai, ToolRegistry $tools): Response
    {
        return Inertia::render('AiObjectives/Form', [
            ...$this->formData($ai, $tools),
            'objective' => AiObjectiveResource::make($aiObjective),
        ]);
    }

    public function store(AiObjectiveRequest $request): RedirectResponse
    {
        AiObjective::create([
            ...$request->validated(),
            'slug' => Str::slug($request->string('name')),
        ]);

        return redirect()->route('ai-objectives.index')->with('success', 'Objetivo criado.');
    }

    public function update(AiObjectiveRequest $request, AiObjective $aiObjective): RedirectResponse
    {
        $aiObjective->update($request->validated());

        return redirect()->route('ai-objectives.index')->with('success', 'Objetivo atualizado.');
    }

    public function destroy(AiObjective $aiObjective): RedirectResponse
    {
        $aiObjective->delete();

        return back()->with('success', 'Objetivo excluído.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(AiManager $ai, ToolRegistry $tools): array
    {
        return [
            'providers' => $ai->options(),
            'tools' => $tools->options(),
            'queues' => ServiceQueueResource::collection(ServiceQueue::query()->ordered()->get()),
        ];
    }
}
