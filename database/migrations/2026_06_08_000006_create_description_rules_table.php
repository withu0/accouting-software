<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('description_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->foreignId('account_id')->constrained();
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('description_rules');
    }
};
