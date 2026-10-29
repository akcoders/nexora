<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('probation_months')->nullable()->after('employment_type');
            $table->date('probation_end_date')->nullable()->index()->after('probation_months');
            $table->timestamp('probation_notified_at')->nullable()->after('probation_end_date');
            $table->timestamp('probation_confirmed_at')->nullable()->after('probation_notified_at');
            $table->foreignId('probation_confirmed_by')->nullable()->after('probation_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('probation_confirmed_by');
            $table->dropColumn(['probation_months', 'probation_end_date', 'probation_notified_at', 'probation_confirmed_at']);
        });
    }
};
