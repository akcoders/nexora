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
        Schema::table('service_checklists', function (Blueprint $table) {
            $table->string('phase', 20)->default('pre')->after('name');
            $table->foreignId('service_type_id')->nullable()->after('product_category_id')->constrained()->nullOnDelete();
            $table->string('equipment_type')->nullable()->after('service_type_id');
            $table->text('description')->nullable()->after('equipment_type');
        });

        Schema::table('checklist_items', function (Blueprint $table) {
            $table->string('category')->nullable()->after('label');
            $table->boolean('photo_required')->default(false)->after('required');
            $table->boolean('remark_allowed')->default(true)->after('photo_required');
            $table->json('allowed_conditions')->nullable()->after('remark_allowed');
            $table->boolean('active')->default(true)->after('allowed_conditions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'photo_required', 'remark_allowed', 'allowed_conditions', 'active']);
        });

        Schema::table('service_checklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_type_id');
            $table->dropColumn(['phase', 'equipment_type', 'description']);
        });
    }
};
