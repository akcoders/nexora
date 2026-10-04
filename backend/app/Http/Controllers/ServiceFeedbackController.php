<?php

namespace App\Http\Controllers;

use App\Models\ServiceFeedback;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceFeedbackController extends Controller
{
    public function show(string $token): View
    {
        $feedback = ServiceFeedback::query()
            ->where('token', $token)
            ->with(['serviceJob.customer', 'serviceJob.technician'])
            ->firstOrFail();

        return view('service-feedback.show', compact('feedback'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $feedback = ServiceFeedback::query()->where('token', $token)->firstOrFail();
        if ($feedback->submitted_at !== null) {
            return redirect()->route('service-feedback.show', $token);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'issue_still_exists' => ['nullable', 'boolean'],
            'request_callback' => ['nullable', 'boolean'],
        ]);
        $feedback->update([
            'rating' => $validated['rating'],
            'comments' => $validated['comments'] ?? null,
            'issue_still_exists' => $request->boolean('issue_still_exists'),
            'request_callback' => $request->boolean('request_callback'),
            'submitted_at' => now(),
        ]);

        return redirect()->route('service-feedback.show', $token)->with('success', 'Thank you for your feedback.');
    }
}
