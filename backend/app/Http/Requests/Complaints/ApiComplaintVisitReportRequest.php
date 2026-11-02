<?php

namespace App\Http\Requests\Complaints;

use App\Models\ComplaintPart;
use App\Models\ComplaintVisit;
use App\Services\ComplaintOptions;
use App\Services\ComplaintService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Visit report from the technician app. The app may send only what changed (for example just
 * `accepted_at` to stamp "Accepted"), so every report field it leaves out is filled from the saved
 * visit, the way the web form posts its pre-filled values. A key sent as null still clears it.
 */
class ApiComplaintVisitReportRequest extends ComplaintVisitReportRequest
{
    private const TIME_FIELDS = ['accepted_at', 'en_route_at', 'arrived_at', 'started_at', 'ended_at'];

    /** A completed visit is refused before validation, so the app gets one clear reason. */
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        abort_unless($this->route('visit')->isOpen(), 422, 'This visit is already completed.');

        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var ComplaintVisit $visit */
        $visit = $this->route('visit');
        $merged = [];

        foreach (ComplaintService::VISIT_REPORT_FIELDS as $field) {
            if (in_array($field, self::TIME_FIELDS, true)) {
                $merged[$field] = $this->has($field) ? $this->appTime($this->input($field)) : $visit->{$field}?->toDateTimeString();
            } elseif (! $this->has($field)) {
                $merged[$field] = $visit->{$field};
            }
        }

        // Checks and readings merge key by key, so one answer can be sent on its own.
        foreach (['checks' => array_keys(ComplaintOptions::VISIT_CHECKS), 'diagnosis' => array_keys(ComplaintOptions::DIAGNOSIS_FIELDS)] as $field => $keys) {
            $sent = $this->input($field);
            if (! $this->has($field) || is_array($sent)) {
                $merged[$field] = array_replace(Arr::only($visit->{$field} ?? [], $keys), $sent ?? []);
            }
        }

        $parts = null;
        foreach (['used_parts' => ComplaintPart::KIND_USED, 'recommended_parts' => ComplaintPart::KIND_RECOMMENDED] as $field => $kind) {
            if (! $this->has($field)) {
                $parts ??= $visit->parts()->orderBy('id')->get();
                $merged[$field] = $parts->where('kind', $kind)->map(fn (ComplaintPart $part): array => $part->only(ComplaintService::PART_FIELDS))->values()->all();
            }
        }

        $this->merge($merged);
    }

    /**
     * A time sent with an offset (e.g. "2026-11-02T04:45:00Z") is converted to app-timezone
     * "Y-m-d H:i:s" so it is stored as local time. Local times and invalid values pass through
     * unchanged for the date rules.
     */
    private function appTime(mixed $value): mixed
    {
        if (! is_string($value) || ! preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', trim($value))) {
            return $value;
        }
        $parsed = date_parse($value);
        if ($parsed['error_count'] > 0 || $parsed['warning_count'] > 0) {
            return $value;
        }

        return Carbon::parse($value)->setTimezone(config('app.timezone'))->toDateTimeString();
    }
}
