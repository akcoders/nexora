<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service Feedback · {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f1f5f9; color: #0f172a; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .card { width: 100%; max-width: 560px; background: #fff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 30px; box-shadow: 0 20px 50px rgba(15,23,42,.08); }
        .logo { width: 54px; height: 54px; display: grid; place-items: center; border-radius: 16px; background: #1e40af; color: white; font-size: 25px; margin-bottom: 22px; }
        h1 { margin: 0 0 8px; font-size: 25px; }
        p { color: #64748b; line-height: 1.55; }
        .job { background: #eff6ff; border-radius: 14px; padding: 14px; margin: 18px 0; font-size: 14px; }
        .stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; margin: 10px 0 20px; }
        .stars input { position: absolute; opacity: 0; }
        .stars label { cursor: pointer; font-size: 40px; color: #cbd5e1; }
        .stars input:checked ~ label, .stars label:hover, .stars label:hover ~ label { color: #f59e0b; }
        label.title { display: block; font-weight: 750; margin: 15px 0 7px; }
        textarea { width: 100%; min-height: 100px; resize: vertical; padding: 13px; border: 1px solid #cbd5e1; border-radius: 12px; font: inherit; }
        .check { display: flex; gap: 10px; align-items: center; padding: 9px 0; }
        .check input { width: 20px; height: 20px; }
        button { width: 100%; border: 0; border-radius: 13px; padding: 15px; margin-top: 14px; background: #1e40af; color: white; font: inherit; font-weight: 750; cursor: pointer; }
        .error { color: #b91c1c; font-size: 13px; margin-top: 6px; }
        .thanks { text-align: center; padding: 18px 0; }
        .thanks .logo { margin: 0 auto 18px; background: #16a34a; }
    </style>
</head>
<body>
<main class="card">
    @if($feedback->submitted_at)
        <div class="thanks"><div class="logo">✓</div><h1>Thank you!</h1><p>Your feedback for {{ $feedback->serviceJob->job_no }} was submitted successfully.</p></div>
    @else
        <div class="logo">❄</div><h1>How was your service?</h1><p>Hello {{ $feedback->serviceJob->customer->name }}, your feedback helps us improve every visit.</p>
        <div class="job"><strong>{{ $feedback->serviceJob->job_no }}</strong><br>{{ $feedback->serviceJob->complaint }}<br><span style="color:#64748b">Technician: {{ $feedback->serviceJob->technician?->name ?? 'Nexora Team' }}</span></div>
        <form method="POST" action="{{ route('service-feedback.store', $feedback->token) }}">@csrf
            <label class="title">Rate your experience</label>
            <div class="stars">@for($rating = 5; $rating >= 1; $rating--)<input id="rating-{{ $rating }}" name="rating" value="{{ $rating }}" type="radio" {{ old('rating') == $rating ? 'checked' : '' }} required><label for="rating-{{ $rating }}">★</label>@endfor</div>
            @error('rating')<div class="error">{{ $message }}</div>@enderror
            <label class="title" for="comments">Comments (optional)</label><textarea id="comments" name="comments" maxlength="2000" placeholder="Tell us what went well or what we can improve">{{ old('comments') }}</textarea>
            <label class="check"><input type="checkbox" name="issue_still_exists" value="1" {{ old('issue_still_exists') ? 'checked' : '' }}> The service issue still exists</label>
            <label class="check"><input type="checkbox" name="request_callback" value="1" {{ old('request_callback') ? 'checked' : '' }}> I would like a callback</label>
            <button type="submit">Submit feedback</button>
        </form>
    @endif
</main>
</body>
</html>
