<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('description');
            $table->string('source');
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
