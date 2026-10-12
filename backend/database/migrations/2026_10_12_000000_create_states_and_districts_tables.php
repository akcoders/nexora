<?php

use Database\Seeders\IndiaLocationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 5)->unique();
            $table->string('gst_code', 2)->nullable();
            $table->string('type', 20)->default('state');
            $table->string('country', 60)->default('India');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['state_id', 'name']);
        });

        (new IndiaLocationSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('states');
    }
};
