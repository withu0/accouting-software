<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 20);
            $table->date('issue_date');
            $table->string('customer_name');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->string('tax_rate', 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
