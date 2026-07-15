<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_card_import_rows')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('credit_card_import_rows'))
            ->pluck('name')
            ->all();

        if (! in_array('credit_card_import_rows_company_id_row_hash_unique', $indexNames, true)) {
            return;
        }

        // MySQL may use the composite unique index to support the company_id FK.
        Schema::table('credit_card_import_rows', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('credit_card_import_rows', function (Blueprint $table) {
            $table->dropUnique('credit_card_import_rows_company_id_row_hash_unique');
        });

        Schema::table('credit_card_import_rows', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('credit_card_import_rows')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('credit_card_import_rows'))
            ->pluck('name')
            ->all();

        if (in_array('credit_card_import_rows_company_id_row_hash_unique', $indexNames, true)) {
            return;
        }

        Schema::table('credit_card_import_rows', function (Blueprint $table) {
            $table->unique(['company_id', 'row_hash']);
        });
    }
};
