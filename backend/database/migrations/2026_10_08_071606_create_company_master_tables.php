<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('organization_id', 50)->unique();
            $table->string('code', 50)->unique();
            $table->string('short_name', 100);
            $table->string('legal_name');
            $table->string('logo_path')->nullable();
            $table->string('company_type', 30)->default('head_office');
            $table->string('organization_type', 50)->nullable();
            $table->string('industry', 80)->default('HVAC');
            $table->json('business_models')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->date('incorporation_date')->nullable();
            $table->json('address')->nullable();
            $table->json('contacts')->nullable();
            $table->json('localization')->nullable();
            $table->json('invoice_settings')->nullable();
            $table->json('compliance')->nullable();
            $table->json('banking')->nullable();
            $table->string('payment_scanner_path')->nullable();
            $table->string('digital_signature_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('company_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('site_id', 60)->unique();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('type', 30)->default('additional_place');
            $table->json('address')->nullable();
            $table->json('contacts')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_sites');
        Schema::dropIfExists('companies');
    }
};
