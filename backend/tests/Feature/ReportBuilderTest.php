<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\ReportDatasets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@nexora.test')->firstOrFail();
    }

    public function test_summary_report_groups_counts_and_labels_enum_values(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('reports.builder.preview'), [
            'dataset' => 'complaints', 'mode' => 'summary', 'group_by' => 'status', 'measure' => 'count', 'chart' => 'bar',
        ])->assertOk();

        $labels = $response->json('result.labels');
        $values = $response->json('result.series.0.data');
        $this->assertContains('Pending', $labels);
        $this->assertSame(3, $values[array_search('Pending', $labels, true)]);
        $this->assertSame(Complaint::count(), $response->json('result.total'));
        $this->assertSame('Number of complaints', $response->json('result.meta.measure'));
    }

    public function test_split_date_grouping_filters_and_sum_measures(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('reports.builder.preview'), [
            'dataset' => 'complaints', 'group_by' => 'registered_at', 'granularity' => 'month', 'split_by' => 'call_type',
            'measure' => 'count', 'chart' => 'stacked', 'preset' => 'last_30',
            'filters' => [['field' => 'priority', 'op' => 'in', 'value' => ['critical', 'high']]],
        ])->assertOk();
        $this->assertNotEmpty($response->json('result.series'));
        $this->assertSame(['Priority is any of Critical, High'], $response->json('result.meta.filters'));
        $expected = Complaint::whereIn('priority', ['critical', 'high'])->where('registered_at', '>=', today()->subDays(29))->count();
        $this->assertSame($expected, array_sum(array_map(fn ($row) => end($row), $response->json('result.rows'))));

        $sum = $this->postJson(route('reports.builder.preview'), [
            'dataset' => 'complaints', 'group_by' => 'payment_status', 'measure' => 'sum', 'measure_field' => 'total_amount', 'chart' => 'pie',
        ])->assertOk();
        $this->assertEquals((float) Complaint::sum('total_amount'), $sum->json('result.total'));
    }

    public function test_list_mode_returns_selected_columns_and_joins_related_names(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('reports.builder.preview'), [
            'dataset' => 'complaints', 'mode' => 'list', 'columns' => ['complaint_no', 'customer', 'technician', 'sla_breached'],
            'list_sort' => 'complaint_no', 'list_dir' => 'asc', 'limit' => 50,
            'filters' => [['field' => 'customer', 'op' => 'contains', 'value' => 'apex']],
        ])->assertOk();

        $this->assertSame(['Complaint no', 'Customer', 'Technician', 'SLA breached'], $response->json('result.columns'));
        $this->assertCount(1, $response->json('result.rows'));
        $this->assertSame('Apex Hospitals', $response->json('result.rows.0.1'));
        $this->assertContains($response->json('result.rows.0.3'), ['Yes', 'No']);
    }

    public function test_invalid_configs_and_unauthorised_datasets_are_rejected(): void
    {
        $this->actingAs($this->admin)->postJson(route('reports.builder.preview'), ['dataset' => 'complaints', 'group_by' => 'password'])->assertJsonValidationErrors('group_by');
        $this->postJson(route('reports.builder.preview'), ['dataset' => 'complaints', 'group_by' => 'status', 'measure' => 'sum', 'measure_field' => 'status'])->assertJsonValidationErrors('measure_field');
        $this->postJson(route('reports.builder.preview'), ['dataset' => 'complaints', 'group_by' => 'status', 'filters' => [['field' => 'age_days', 'op' => 'gt', 'value' => 'abc']]])->assertJsonValidationErrors('filters.0.value');

        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $this->actingAs($technician)->postJson(route('reports.builder.preview'), ['dataset' => 'payroll', 'group_by' => 'department'])->assertJsonValidationErrors('dataset');
        $own = $this->postJson(route('reports.builder.preview'), ['dataset' => 'complaints', 'group_by' => 'status'])->assertOk();
        $this->assertSame(Complaint::query()->visibleTo($technician)->count(), $own->json('result.total'));
        $this->get(route('reports.builder'))->assertOk()->assertDontSee('Payroll entries');
    }

    public function test_reports_can_be_saved_shared_pinned_to_the_dashboard_and_only_changed_by_the_owner(): void
    {
        $config = ['dataset' => 'complaints', 'group_by' => 'call_type', 'chart' => 'donut'];
        $this->actingAs($this->admin)->postJson(route('reports.saved.store'), ['name' => 'Calls by type', 'is_shared' => true, 'pinned' => true, 'config' => $config])->assertCreated();
        $report = SavedReport::firstOrFail();
        $this->assertTrue($report->pinned);
        $this->assertSame('donut', $report->config['chart']);

        $this->get(route('dashboard'))->assertOk()->assertSee('Calls by type')->assertSee(route('reports.saved.data', $report), false);
        $this->getJson(route('reports.saved.data', $report))->assertOk()->assertJsonPath('result.chart', 'donut');
        $this->get(route('reports.builder', ['report' => $report->id]))->assertOk()->assertSee('Calls by type');

        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('Manager');
        $this->actingAs($manager)->getJson(route('reports.saved.data', $report))->assertOk();
        $this->putJson(route('reports.saved.update', $report), ['name' => 'Hijack', 'config' => $config])->assertForbidden();
        $this->patchJson(route('reports.saved.pin', $report))->assertForbidden();

        $this->actingAs($this->admin)->patchJson(route('reports.saved.pin', $report))->assertOk()->assertJsonPath('report.pinned', false);
        $this->delete(route('reports.saved.destroy', $report))->assertRedirect(route('reports.builder'));
        $this->assertDatabaseCount('saved_reports', 0);
    }

    public function test_every_dataset_runs_in_summary_and_list_mode(): void
    {
        foreach (ReportDatasets::availableFor($this->admin) as $key => $dataset) {
            $first = array_key_first($dataset['fields']);
            $this->actingAs($this->admin)->postJson(route('reports.builder.preview'), ['dataset' => $key, 'group_by' => $first, 'preset' => 'this_year'])->assertOk();
            $this->postJson(route('reports.builder.preview'), ['dataset' => $key, 'mode' => 'list', 'columns' => array_keys($dataset['fields'])])->assertOk();
        }
    }

    public function test_dashboard_widgets_can_be_hidden_per_user(): void
    {
        $this->actingAs($this->admin)->postJson(route('dashboard.preferences'), ['hidden' => ['root_causes', 'attendance']])->assertOk();
        $this->assertSame(['root_causes', 'attendance'], $this->admin->fresh()->dashboard_preferences['hidden']);
        $this->get(route('dashboard'))->assertOk()->assertSee('data-widget="root_causes" data-hidden="1"', false)->assertSee('data-widget="complaint_trend" data-hidden="0"', false);
        $this->postJson(route('dashboard.preferences'), ['hidden' => ['not_a_widget']])->assertJsonValidationErrors('hidden.0');
    }
}
