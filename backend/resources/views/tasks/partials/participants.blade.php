@php
    $observers = $task->members->where('role', 'observer')->filter(fn ($member) => $member->user)->unique('user_id');
    $previousOwners = $task->members->where('role', 'previous_assignee')->where('user_id', '!=', $task->assigned_to)->filter(fn ($member) => $member->user)->unique('user_id');
@endphp
@if($observers->isNotEmpty())
    <div class="nx-participants mt-4"><div class="nx-participants-label"><span>Observers</span><span>{{ $observers->count() }}</span></div><div class="nx-avatar-stack flex-wrap">@foreach($observers as $member)<x-person-avatar :user="$member->user" :size="36" detail="Observer" />@endforeach</div></div>
@endif
@if($previousOwners->isNotEmpty())
    <div class="nx-participants mt-3"><div class="nx-participants-label"><span>Previous owners</span><span>{{ $previousOwners->count() }}</span></div><div class="nx-avatar-stack flex-wrap">@foreach($previousOwners as $member)<x-person-avatar :user="$member->user" :size="32" detail="Previous owner" />@endforeach</div></div>
@endif
