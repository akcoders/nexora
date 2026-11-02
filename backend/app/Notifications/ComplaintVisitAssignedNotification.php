<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\ComplaintVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintVisitAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Complaint $complaint, public ComplaintVisit $visit) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Complaint visit '.$this->visit->label().' assigned',
            'message' => $this->complaint->complaint_no.' · '.$this->complaint->customer?->name.' · '.$this->complaint->nature_of_complaint
                .($this->visit->appointment_at ? ' · '.$this->visit->appointment_at->format('d M, h:i A') : ''),
            'complaint_id' => $this->complaint->id,
            'complaint_visit_id' => $this->visit->id,
            'url' => route('complaints.show', $this->complaint),
            'type' => 'complaint_visit',
            'icon' => 'clipboard-list',
        ];
    }
}
