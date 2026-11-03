<x-app-layout>
    <x-slot name="title">Notifications</x-slot>
    <x-slot name="pageTitle">Notifications</x-slot>
    <x-slot name="breadcrumb">Notifications</x-slot>

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Inbox</div><h2>Notifications</h2><p>Assignments, complaint visits, approvals and system activity.</p></div>
        <div class="nx-page-actions"><form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="btn btn-light border"><i data-lucide="check-check"></i>Mark all read</button></form></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <section class="nx-card nx-datatable-card">
                <div class="nx-card-header">
                    <div><h3 class="nx-card-title"><i data-lucide="bell"></i>My notifications</h3><p class="nx-card-subtitle">Newest first</p></div>
                    <span class="nx-count">{{ $notifications->count() }}</span>
                </div>
                <table class="table nx-table" data-datatable data-dt-title="Notifications" data-dt-order='[[3,"desc"]]' data-dt-empty="No personal notifications yet.">
                    <thead><tr><th data-priority="1">Notification</th><th>Details</th><th>Status</th><th>Received</th><th class="no-export no-sort text-end" data-orderable="false"></th></tr></thead>
                    <tbody>
                        @foreach($notifications as $notification)
                            @php $url = $notification->data['url'] ?? (isset($notification->data['task_id']) ? route('tasks.show', $notification->data['task_id']) : null); @endphp
                            <tr>
                                <td style="min-width:200px"><div class="d-flex align-items-center gap-2"><span class="nx-metric-icon flex-shrink-0" style="width:32px;height:32px"><i data-lucide="{{ $notification->data['icon'] ?? 'bell' }}" style="width:16px"></i></span><span class="nx-row-title">{{ $notification->data['title'] ?? 'Notification' }}</span></div></td>
                                <td style="min-width:240px" class="text-secondary">{{ $notification->data['message'] ?? '' }}</td>
                                <td><x-pill :tone="$notification->read_at ? 'secondary' : 'primary'" :label="$notification->read_at ? 'Read' : 'New'" /></td>
                                <td data-order="{{ $notification->created_at->timestamp }}" class="text-nowrap">{{ $notification->created_at->format('d M Y, h:i A') }}<span class="nx-row-sub">{{ $notification->created_at->diffForHumans() }}</span></td>
                                <td class="text-end">@if($url)<a class="btn btn-sm btn-light border" href="{{ $url }}">Open</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="nx-card">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="activity"></i>Recent system activity</h3><p class="nx-card-subtitle">Latest 20 events in the workspace</p></div></div>
                <div class="nx-card-body">
                    @if($activities->isEmpty())
                        <div class="nx-empty py-4"><i data-lucide="activity"></i><div>No recent activity.</div></div>
                    @else
                        <ol class="nx-tl">
                            @foreach($activities as $activity)
                                <li><span class="nx-tl-icon tone-secondary"><i data-lucide="dot"></i></span><div class="nx-tl-head"><span class="nx-tl-title">{{ str($activity->event)->replace('.', ' ')->headline() }}</span><span class="nx-tl-meta">{{ $activity->created_at->diffForHumans() }}</span></div></li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
