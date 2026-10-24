<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_floor_plans', function (Blueprint $table) {
            $table->uuid('building_uuid')->nullable()->after('customer_branch_id');
            $table->string('building_name')->nullable()->after('building_uuid');
            $table->unsignedSmallInteger('floor_number')->nullable()->after('floor_label');
            $table->string('image_path')->nullable()->change();
            $table->index(['customer_id', 'building_uuid', 'floor_number'], 'customer_floor_building_index');
        });

        Schema::create('customer_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_floor_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['customer_floor_plan_id', 'name']);
        });

        Schema::table('customer_equipments', function (Blueprint $table) {
            $table->foreignId('customer_room_id')->nullable()->after('customer_floor_plan_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_equipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_room_id');
        });

        Schema::dropIfExists('customer_rooms');

        DB::table('customer_floor_plans')->whereNull('image_path')->delete();

        Schema::table('customer_floor_plans', function (Blueprint $table) {
            $table->dropIndex('customer_floor_building_index');
            $table->dropColumn(['building_uuid', 'building_name', 'floor_number']);
            $table->string('image_path')->nullable(false)->change();
        });
    }
};
