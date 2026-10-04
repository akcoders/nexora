<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceJobMigrationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_tables_migration_recovers_after_a_partial_database_failure(): void
    {
        $migration = require database_path('migrations/2026_10_04_115342_create_service_job_work_tables.php');
        $migration->down();

        Schema::create('service_inspections', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('service_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_inspection_id');
            $table->unsignedBigInteger('checklist_item_id')->nullable();
        });

        try {
            $migration->up();

            $this->assertTrue(Schema::hasIndex(
                'service_inspection_items',
                ['service_inspection_id', 'checklist_item_id'],
                'unique',
            ));
            $this->assertTrue(Schema::hasTable('service_job_photos'));
        } finally {
            $migration->down();
            $migration->up();
        }
    }
}
