<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Notifications\ProbationEndedNotification;
use App\Services\OneSignalService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class NotifyProbationEnd extends Command
{
    protected $signature = 'employees:notify-probation-end';

    protected $description = 'Email and push HR when an employee\'s probation period has ended and needs review';

    public function handle(OneSignalService $oneSignal): int
    {
        $due = EmployeeProfile::query()->probationDue()->with(['user', 'hrManager'])->get();
        $hrTeam = $this->hrTeam();

        foreach ($due as $profile) {
            if (! $profile->user) {
                continue;
            }

            $recipients = $hrTeam
                ->when($profile->hrManager, fn (Collection $users) => $users->push($profile->hrManager))
                ->unique('id')
                ->reject(fn (User $user) => $user->id === $profile->user_id);

            $notification = new ProbationEndedNotification($profile);
            foreach ($recipients as $recipient) {
                $recipient->notify($notification);
                $oneSignal->sendToUser($recipient, $notification->pushTitle(), $notification->pushMessage(), ['type' => 'probation_review', 'employee_id' => $profile->user_id]);
            }

            $profile->forceFill(['probation_notified_at' => now()])->save();
            $this->info("Notified {$recipients->count()} HR user(s) about {$profile->user->name}.");
        }

        $this->info("Processed {$due->count()} probation review(s).");

        return self::SUCCESS;
    }

    /**
     * Active HR users: the HR department first, otherwise everyone who can update employees.
     *
     * @return Collection<int, User>
     */
    private function hrTeam(): Collection
    {
        $canManage = User::query()->active()->permission('employees.update')->get();
        $hrDepartment = $canManage->filter(fn (User $user) => strcasecmp((string) $user->department, 'HR') === 0);

        return ($hrDepartment->isNotEmpty() ? $hrDepartment : $canManage)->values();
    }
}
