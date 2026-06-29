<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('consumption_tax_method')->default('standard')->after('fiscal_year_start_month');
            $table->string('simplified_tax_industry')->nullable()->after('consumption_tax_method');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['consumption_tax_method', 'simplified_tax_industry']);
        });
    }
};
