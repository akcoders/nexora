<?php

namespace App\Notifications;

use App\Models\EmployeeProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProbationEndedNotification extends Notification
{
    use Queueable;

    public function __construct(public EmployeeProfile $profile) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->profile->user;
        $endDate = $this->profile->probation_end_date?->format('d M Y') ?? 'today';

        return (new MailMessage)
            ->subject('Probation review due: '.$employee->name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($employee->name.' ('.$employee->employee_code.', '.($employee->designation ?: 'designation not set').') completed the '.$this->probationLength().' probation period on '.$endDate.'.')
            ->line('Please review their performance and confirm them as a permanent employee, or extend the probation from the employee master.')
            ->action('Review employee', route('employees.show', $employee))
            ->line('The employment status stays "Probation" until HR confirms it.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $employee = $this->profile->user;

        return [
            'title' => 'Probation review due',
            'message' => $employee->name.' finished probation on '.($this->profile->probation_end_date?->format('d M Y') ?? 'today').'. Confirm or extend from the employee profile.',
            'employee_id' => $employee->id,
            'url' => route('employees.show', $employee),
            'type' => 'probation_review',
            'icon' => 'timer',
        ];
    }

    public function pushTitle(): string
    {
        return 'Probation review due';
    }

    public function pushMessage(): string
    {
        return $this->profile->user->name.' completed probation on '.($this->profile->probation_end_date?->format('d M Y') ?? 'today').'.';
    }

    private function probationLength(): string
    {
        $months = $this->profile->probation_months;

        return $months ? $months.'-month' : '';
    }
}
