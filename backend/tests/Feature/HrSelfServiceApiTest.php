<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrSelfServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_hr_summary_and_apply_for_leave(): void
    {
        $this->seed();
        $user = User::where('email', 'technician@nexora.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/hr-self-service')->assertOk()->assertJsonStructure(['user', 'leave_balances', 'holidays', 'vouchers', 'payslips']);
        $type = LeaveType::where('code', 'CL')->firstOrFail();
        $from = now()->next('Monday');
        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id,
            'from_date' => $from->toDateString(),
            'to_date' => $from->toDateString(),
            'reason' => 'Family appointment',
        ])->assertCreated();

        $this->assertDatabaseHas('leave_requests', ['user_id' => $user->id, 'leave_type_id' => $type->id, 'status' => 'pending']);
    }

    public function test_employee_can_submit_expense_voucher_with_receipt(): void
    {
        Storage::fake('public');
        $this->seed();
        $user = User::where('email', 'technician@nexora.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->post('/api/v1/expense-vouchers', [
            'expense_date' => today()->toDateString(), 'category' => 'Travel', 'amount' => 850,
            'description' => 'Customer site travel', 'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseHas('expense_vouchers', ['user_id' => $user->id, 'amount' => 850, 'status' => 'pending']);
    }
}
