<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('line_no');
        });

        $issueDates = DB::table('invoices')->pluck('issue_date', 'id');

        foreach (DB::table('invoice_lines')->select('id', 'invoice_id')->get() as $line) {
            DB::table('invoice_lines')->where('id', $line->id)->update([
                'transaction_date' => $issueDates[$line->invoice_id] ?? now()->toDateString(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('transaction_date');
        });
    }
};
