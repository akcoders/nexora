<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_sidebar_pages_render_for_an_administrator(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $task = Task::firstOrFail();

        $routes = [
            route('dashboard'),
            route('customers.index'),
            route('customer-groups.index'),
            route('products.index'),
            route('checklists.index'),
            route('attendance.index'),
            route('tasks.index'),
            route('tasks.show', $task),
            route('reports.index'),
            route('users.index'),
            route('employees.index'),
            route('employees.show', $admin),
            route('companies.index'),
            route('roles.index'),
            route('hr.index'),
            route('payroll.index'),
            route('notifications.index'),
            route('settings.index'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
