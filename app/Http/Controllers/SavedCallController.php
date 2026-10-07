<?php

namespace App\Http\Controllers;

use App\Models\SavedCall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedCallController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $call = SavedCall::query()->create($this->attributes($request));

        return redirect()
            ->route('calls.show', $call)
            ->with('status', 'Bookmarked.');
    }

    public function update(Request $request, SavedCall $savedCall): RedirectResponse
    {
        $savedCall->update($this->attributes($request));

        return redirect()
            ->route('calls.show', $savedCall)
            ->with('status', 'Bookmarked.');
    }

    public function destroy(SavedCall $savedCall): RedirectResponse
    {
        $savedCall->delete();

        return redirect()
            ->route('run.create')
            ->with('status', 'Removed.');
    }

    /**
     * @return array{name: string, method: string, path: string, model: ?string, body_mode: string, state: ?string, questions: ?array<int, array<string, mixed>>, body: ?string}
     */
    private function attributes(Request $request): array
    {
        $form = $request->input('body_mode') === 'form';
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'path' => ['required', 'string', 'max:2048'],
            'model' => ['nullable', 'string', 'max:255'],
            'body_mode' => ['required', Rule::in(['form', 'json'])],
        ];

        if ($form) {
            $rules['state'] = ['nullable', 'string', 'max:150000'];
            $rules['questions'] = ['nullable', 'array', 'max:64'];
            $rules['questions.*.name'] = ['nullable', 'string', 'max:100'];
            $rules['questions.*.type'] = ['nullable', 'string', 'max:20'];
            $rules['questions.*.instructions'] = ['nullable', 'string', 'max:2000'];
            $rules['questions.*.true'] = ['nullable', 'string', 'max:2000'];
            $rules['questions.*.false'] = ['nullable', 'string', 'max:2000'];
            $rules['questions.*.options'] = ['nullable', 'array', 'max:255'];
            $rules['questions.*.options.*.name'] = ['nullable', 'string', 'max:200'];
            $rules['questions.*.options.*.description'] = ['nullable', 'string', 'max:2000'];
            $rules['questions.*.levels'] = ['nullable', 'array', 'max:26'];
            $rules['questions.*.levels.*'] = ['nullable', 'string', 'max:200'];
        } else {
            $rules['body'] = ['nullable', 'string', 'max:150000'];
        }

        $validated = $request->validate($rules, [
            'name.required' => 'Enter a name for this bookmark.',
            'method.in' => 'Choose a request method.',
            'path.required' => 'Enter a path.',
            'questions.*.levels.max' => 'Score questions allow at most 26 levels.',
        ]);

        return [
            'name' => $validated['name'],
            'method' => $validated['method'],
            'path' => $validated['path'],
            'model' => $validated['model'] ?? null,
            'body_mode' => $form ? 'form' : 'json',
            'state' => $form ? ($validated['state'] ?? null) : null,
            'questions' => $form ? ($validated['questions'] ?? null) : null,
            'body' => $form ? null : ($validated['body'] ?? null),
        ];
    }
}
