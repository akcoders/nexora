<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->decimal('default_price', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspection_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name')->unique();
            $table->string('color', 20)->default('secondary');
            $table->boolean('is_issue')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('equipment_type')->nullable();
            $table->decimal('standard_price', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'category']);
        });

        Schema::create('service_master_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('code', 50);
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['type', 'code']);
            $table->index(['type', 'active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_master_options');
        Schema::dropIfExists('service_catalog_items');
        Schema::dropIfExists('inspection_conditions');
        Schema::dropIfExists('service_types');
    }
};
