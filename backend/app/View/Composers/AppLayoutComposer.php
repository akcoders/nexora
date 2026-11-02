<?php

namespace App\View\Composers;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\User;
use App\Services\EmployeeMasterOptions;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Shared data for the application shell: the permission-aware sidebar menu, notification
 * dropdown, follow-up badges and the interface language switcher.
 */
class AppLayoutComposer
{
    public function compose(View $view): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $unread = $user->unreadNotifications()->count();
        $pendingAttendance = $user->can('attendance.read') || $user->can('attendance.approve') ? Attendance::where('status', 'pending')->count() : 0;
        $followUpsDue = $user->canAny(['complaints.read', 'complaints.view_own']) ? Complaint::query()->visibleTo($user)->followUpDue()->count() : 0;
        $preferred = EmployeeMasterOptions::preferredLanguageFor($user);

        $view->with([
            'nxUser' => $user,
            'nxUnread' => $unread,
            'nxNotifications' => $user->notifications()->latest()->limit(6)->get(),
            'nxPendingAttendance' => $pendingAttendance,
            'nxFollowUpsDue' => $followUpsDue,
            'nxMenu' => $this->menu($user, $unread, $pendingAttendance, $followUpsDue),
            'nxLanguageOptions' => EmployeeMasterOptions::switcherLanguagesFor($user),
            'nxPreferredLanguage' => $preferred,
            'nxLanguageConfig' => [
                'userId' => $user->id,
                'preferred' => $preferred,
                'preferredCode' => EmployeeMasterOptions::languageCode($preferred),
                'options' => EmployeeMasterOptions::switcherLanguagesFor($user),
                'saveUrl' => route('profile.language'),
            ],
        ]);
    }

    /**
     * Sidebar sections. Items the user cannot open are left out, and empty sections disappear.
     *
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    private function menu(User $user, int $unread, int $pendingAttendance, int $followUpsDue): array
    {
        $can = fn (string ...$permissions): bool => $user->canAny($permissions);
        $isWorkflow = request()->routeIs('workflows.*', 'tasks.*');

        $sections = [
            ['key' => 'main', 'label' => 'Main', 'items' => [
                ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
                ['label' => 'Reports & analytics', 'icon' => 'chart-no-axes-combined', 'url' => route('reports.index'), 'active' => request()->routeIs('reports.index')],
                ['label' => 'Report builder', 'icon' => 'table-properties', 'url' => route('reports.builder'), 'active' => request()->routeIs('reports.builder*', 'reports.saved.*')],
                ['label' => 'Notifications', 'icon' => 'bell', 'url' => route('notifications.index'), 'active' => request()->routeIs('notifications.*'), 'badge' => $unread, 'tone' => 'danger'],
            ]],
            ['key' => 'complaints', 'label' => 'Complaints', 'items' => [
                ['label' => 'Complaint register', 'icon' => 'clipboard-list', 'url' => route('complaints.index'), 'active' => request()->routeIs('complaints.index', 'complaints.show', 'complaints.visits.*', 'complaints.print'), 'show' => $can('complaints.read', 'complaints.view_own')],
                ['label' => 'Register complaint', 'icon' => 'circle-plus', 'url' => route('complaints.create'), 'active' => request()->routeIs('complaints.create'), 'show' => $can('complaints.create')],
                ['label' => 'Pending management', 'icon' => 'hourglass', 'url' => route('complaints.pending'), 'active' => request()->routeIs('complaints.pending'), 'show' => $can('complaints.read', 'complaints.view_own'), 'badge' => $followUpsDue, 'tone' => 'warning'],
                ['label' => 'Complaint masters', 'icon' => 'list-tree', 'url' => route('complaint-masters.index'), 'active' => request()->routeIs('complaint-masters.*'), 'show' => $can('complaints.read')],
            ]],
            ['key' => 'customers', 'label' => 'Customers', 'items' => [
                ['label' => 'Customers', 'icon' => 'building-2', 'url' => route('customers.index'), 'active' => request()->routeIs('customers.*')],
                ['label' => 'Customer groups', 'icon' => 'tags', 'url' => route('customer-groups.index'), 'active' => request()->routeIs('customer-groups.*')],
            ]],
            ['key' => 'service', 'label' => 'Service & catalog', 'items' => [
                ['label' => 'Service jobs', 'icon' => 'wrench', 'url' => route('service-jobs.index'), 'active' => request()->routeIs('service-jobs.*')],
                ['label' => 'Products', 'icon' => 'package', 'url' => route('products.index'), 'active' => request()->routeIs('products.*', 'product-categories.*')],
                ['label' => 'Service checklists', 'icon' => 'clipboard-check', 'url' => route('checklists.index'), 'active' => request()->routeIs('checklists.*')],
                ['label' => 'Service masters', 'icon' => 'sliders-horizontal', 'url' => route('service-masters.index'), 'active' => request()->routeIs('service-masters.*'), 'show' => $can('checklists.read')],
            ]],
            ['key' => 'work', 'label' => 'Work management', 'items' => [
                ['label' => 'Internal workflows', 'icon' => 'git-branch', 'url' => route('workflows.index', ['scope' => 'open']), 'active' => $isWorkflow && ! request()->routeIs('workflows.board', 'tasks.board')],
                ['label' => 'Workflow board', 'icon' => 'square-kanban', 'url' => route('workflows.board'), 'active' => request()->routeIs('workflows.board', 'tasks.board')],
            ]],
            ['key' => 'people', 'label' => 'HR & people', 'items' => [
                ['label' => 'Employees', 'icon' => 'contact-round', 'url' => route('employees.index'), 'active' => request()->routeIs('employees.*'), 'show' => $can('employees.read')],
                ['label' => 'Attendance', 'icon' => 'map-pin-check', 'url' => route('attendance.index'), 'active' => request()->routeIs('attendance.*', 'premises.*'), 'badge' => $pendingAttendance, 'tone' => 'warning'],
                ['label' => 'Leave & expenses', 'icon' => 'calendar-heart', 'url' => route('hr.index'), 'active' => request()->routeIs('hr.*'), 'show' => $can('hr.read')],
                ['label' => 'Payroll', 'icon' => 'wallet-cards', 'url' => route('payroll.index'), 'active' => request()->routeIs('payroll.*'), 'show' => $can('payroll.read')],
            ]],
            ['key' => 'admin', 'label' => 'Administration', 'items' => [
                ['label' => 'Company master', 'icon' => 'landmark', 'url' => route('companies.index'), 'active' => request()->routeIs('companies.*'), 'show' => $can('companies.read')],
                ['label' => 'User accounts', 'icon' => 'users-round', 'url' => route('users.index'), 'active' => request()->routeIs('users.*')],
                ['label' => 'Roles & permissions', 'icon' => 'shield-check', 'url' => route('roles.index'), 'active' => request()->routeIs('roles.*'), 'show' => $can('roles.read')],
                ['label' => 'Settings', 'icon' => 'settings', 'url' => route('settings.index'), 'active' => request()->routeIs('settings.*')],
            ]],
        ];

        return collect($sections)
            ->map(fn (array $section) => [...$section, 'items' => collect($section['items'])->filter(fn (array $item) => $item['show'] ?? true)->map(fn (array $item) => Arr::except($item, 'show'))->values()->all()])
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }
}
