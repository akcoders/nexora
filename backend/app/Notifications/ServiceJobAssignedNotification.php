<?php

namespace App\Notifications;

use App\Models\ServiceJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServiceJobAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public ServiceJob $serviceJob) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Service Job Assigned',
            'message' => ($this->serviceJob->job_no ?? 'Service job').' · '.$this->serviceJob->customer->name,
            'service_job_id' => $this->serviceJob->id,
            'job_no' => $this->serviceJob->job_no,
            'type' => 'service_job',
            'icon' => 'wrench',
        ];
    }
}
