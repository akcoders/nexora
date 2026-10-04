<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_floor_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('floor_label')->nullable();
            $table->string('image_path');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'active']);
        });

        Schema::table('customer_equipments', function (Blueprint $table) {
            $table->foreignId('customer_floor_plan_id')->nullable()->after('customer_branch_id')->constrained()->nullOnDelete();
            $table->decimal('plan_x', 5, 2)->nullable()->after('location');
            $table->decimal('plan_y', 5, 2)->nullable()->after('plan_x');
        });
    }

    public function down(): void
    {
        Schema::table('customer_equipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_floor_plan_id');
            $table->dropColumn(['plan_x', 'plan_y']);
        });

        Schema::dropIfExists('customer_floor_plans');
    }
};
