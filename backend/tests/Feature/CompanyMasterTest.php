<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_own_company_master_and_sites(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        $this->actingAs($admin)->post(route('companies.store'), [
            'organization_id' => 'CCSPL-BR-002', 'code' => 'CCSPL-PUN', 'short_name' => 'Classic Pune',
            'legal_name' => 'Classic Cooling Systems Pune', 'company_type' => 'branch', 'parent_id' => Company::where('company_type', 'head_office')->value('id'),
            'industry' => 'HVAC', 'business_models' => ['service', 'amc'], 'status' => 'active',
            'address' => ['city' => 'Pune', 'state' => 'Maharashtra', 'country' => 'India'],
            'sites' => [['site_id' => 'CCSPL-PUN-S01', 'code' => 'PUN-WH', 'name' => 'Pune Warehouse', 'type' => 'warehouse']],
        ])->assertRedirect(route('companies.index'));

        $this->assertDatabaseHas('companies', ['organization_id' => 'CCSPL-BR-002', 'company_type' => 'branch']);
        $this->assertDatabaseHas('company_sites', ['site_id' => 'CCSPL-PUN-S01', 'type' => 'warehouse']);
    }
}
