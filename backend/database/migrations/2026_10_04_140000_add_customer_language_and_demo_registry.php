<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mother_tongue', 50)->nullable()->after('name');
        });

        Schema::create('demo_data_records', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('scenario_key', 50);
            $table->timestamps();
            $table->unique(['model_type', 'model_id'], 'demo_records_model_unique');
            $table->index('scenario_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_data_records');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('mother_tongue'));
    }
};
