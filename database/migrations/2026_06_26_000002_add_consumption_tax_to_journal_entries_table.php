<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('consumption_tax_category')->nullable()->after('source');
            $table->boolean('has_qualified_invoice')->nullable()->after('consumption_tax_category');
        });

        DB::table('journal_entries')
            ->join('bank_import_rows', 'journal_entries.id', '=', 'bank_import_rows.journal_entry_id')
            ->where('journal_entries.source', 'bank_csv')
            ->where('bank_import_rows.deposit_amount', '>', 0)
            ->update([
                'journal_entries.consumption_tax_category' => 'taxable_sales_10',
                'journal_entries.has_qualified_invoice' => null,
            ]);

        DB::table('journal_entries')
            ->join('bank_import_rows', 'journal_entries.id', '=', 'bank_import_rows.journal_entry_id')
            ->where('journal_entries.source', 'bank_csv')
            ->where('bank_import_rows.withdrawal_amount', '>', 0)
            ->update([
                'journal_entries.consumption_tax_category' => 'taxable_purchase_10',
                'journal_entries.has_qualified_invoice' => true,
            ]);

        DB::table('journal_entries')
            ->where('source', 'advance_expense')
            ->whereNull('consumption_tax_category')
            ->update(['consumption_tax_category' => 'taxable_purchase_10', 'has_qualified_invoice' => true]);

        DB::table('journal_entries')
            ->where('source', 'transfer')
            ->whereNull('consumption_tax_category')
            ->update(['consumption_tax_category' => 'out_of_scope']);
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['consumption_tax_category', 'has_qualified_invoice']);
        });
    }
};
