<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->string('consumption_tax_category')->nullable()->after('credit');
        });

        if (! Schema::hasColumn('journal_entries', 'consumption_tax_category')) {
            return;
        }

        $transferEntries = DB::table('journal_entries')
            ->where('source', 'transfer')
            ->whereNotNull('consumption_tax_category')
            ->get(['id', 'consumption_tax_category']);

        foreach ($transferEntries as $entry) {
            DB::table('journal_lines')
                ->where('journal_entry_id', $entry->id)
                ->update(['consumption_tax_category' => $entry->consumption_tax_category]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn('consumption_tax_category');
        });
    }
};
