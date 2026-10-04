<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_branches', function (Blueprint $table) {
            $table->string('landmark')->nullable()->after('address_line_2');
            $table->string('area')->nullable()->after('landmark');
            $table->string('district')->nullable()->after('city');
            $table->string('country')->default('India')->after('pin_code');
            $table->string('contact_no_1', 20)->nullable()->after('longitude');
            $table->string('contact_no_2', 20)->nullable()->after('contact_no_1');
            $table->string('email_1')->nullable()->after('contact_no_2');
            $table->string('email_2')->nullable()->after('email_1');
            $table->string('gst_registration_type', 20)->nullable()->after('gstin');
            $table->text('site_access_instructions')->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('customer_branches', function (Blueprint $table) {
            $table->dropColumn([
                'landmark',
                'area',
                'district',
                'country',
                'contact_no_1',
                'contact_no_2',
                'email_1',
                'email_2',
                'gst_registration_type',
                'site_access_instructions',
            ]);
        });
    }
};
