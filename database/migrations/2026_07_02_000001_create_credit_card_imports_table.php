<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('detected_format')->nullable();
            $table->string('card_name')->nullable();
            $table->date('payment_date')->nullable();
            $table->unsignedBigInteger('billing_amount')->nullable();
            $table->string('status');
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_card_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('row_hash');
            $table->date('transaction_date');
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->foreignId('suggested_account_id')->nullable()->constrained('accounts');
            $table->foreignId('journal_entry_id')->nullable()->constrained();
            $table->string('status');
            $table->timestamps();

            $table->unique(['company_id', 'row_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_import_rows');
        Schema::dropIfExists('credit_card_imports');
    }
};
