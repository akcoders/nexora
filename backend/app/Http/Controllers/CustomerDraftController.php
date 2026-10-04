<?php

namespace App\Http\Controllers;

use App\Models\CustomerDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerDraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_step' => ['nullable', 'integer', 'between:1,7'],
            'data' => ['nullable', 'array'],
        ]);

        $draft = CustomerDraft::create([
            'uuid' => Str::uuid(),
            'user_id' => $request->user()->id,
            'current_step' => $validated['current_step'] ?? 1,
            'data' => $validated['data'] ?? [],
        ]);

        return response()->json(['message' => 'Draft saved.', 'draft' => $draft], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $draft = $this->ownedDraft($request, $uuid);
        $validated = $request->validate([
            'current_step' => ['required', 'integer', 'between:1,7'],
            'data' => ['required', 'array'],
        ]);

        $draft->update([
            'current_step' => $validated['current_step'],
            'data' => array_replace_recursive($draft->data ?? [], $validated['data']),
        ]);

        return response()->json(['message' => 'Draft updated.', 'draft' => $draft->fresh()]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        return response()->json(['draft' => $this->ownedDraft($request, $uuid)]);
    }

    private function ownedDraft(Request $request, string $uuid): CustomerDraft
    {
        return CustomerDraft::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
    }
}
