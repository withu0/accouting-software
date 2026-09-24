<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equity_statement_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year_id']);
        });

        Schema::create('equity_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equity_statement_override_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->integer('capital')->default(0);
            $table->integer('capital_surplus')->default(0);
            $table->integer('retained_earnings')->default(0);
            $table->integer('treasury')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_statement_lines');
        Schema::dropIfExists('equity_statement_overrides');
    }
};
