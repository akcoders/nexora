<?php

namespace Tests\Feature;

use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalarySlipTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_signed_salary_slip_url_downloads_pdf_and_unsigned_url_is_forbidden(): void
    {
        $employee = User::factory()->create(['employee_code' => 'EMP-PDF-01']);
        $run = PayrollRun::factory()->create(['payroll_month' => '2026-09', 'status' => 'approved']);
        $entry = PayrollEntry::factory()->create([
            'payroll_run_id' => $run->id,
            'user_id' => $employee->id,
            'status' => 'approved',
        ]);
        $signedUrl = URL::temporarySignedRoute('salary-slips.download', now()->addHour(), ['payrollEntry' => $entry]);

        $this->get($signedUrl)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('salary-slip-2026-09-EMP-PDF-01.pdf');

        $this->get(route('salary-slips.download', $entry))->assertForbidden();
    }

    public function test_employee_hr_summary_contains_download_url_for_own_approved_salary_slip(): void
    {
        $employee = User::factory()->create();
        $otherEmployee = User::factory()->create();
        $run = PayrollRun::factory()->create(['payroll_month' => '2026-09', 'status' => 'approved']);
        $ownEntry = PayrollEntry::factory()->create(['payroll_run_id' => $run->id, 'user_id' => $employee->id, 'status' => 'approved']);
        PayrollEntry::factory()->create(['payroll_run_id' => $run->id, 'user_id' => $otherEmployee->id, 'status' => 'approved']);
        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/hr-self-service')
            ->assertOk()
            ->assertJsonCount(1, 'payslips')
            ->assertJsonPath('payslips.0.id', $ownEntry->id);

        $downloadUrl = $response->json('payslips.0.download_url');
        $this->assertIsString($downloadUrl);
        $this->assertTrue(URL::hasValidSignature(Request::create($downloadUrl)));
    }
}
