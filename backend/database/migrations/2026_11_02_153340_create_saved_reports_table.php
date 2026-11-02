<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('dataset', 40);
            $table->json('config');
            $table->boolean('is_shared')->default(false);
            $table->boolean('pinned')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'pinned']);
            $table->index('is_shared');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
