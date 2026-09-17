<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->decimal('overpayment_amount', 18, 2)->default(0)->after('fee_portion');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete()->after('reversal_reason');
            $table->date('reversal_date')->nullable()->after('reversed_by');
        });

        Schema::table('loan_repayment_allocations', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->after('fee_allocation');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn(['overpayment_amount', 'reversed_by', 'reversal_date']);
        });

        Schema::table('loan_repayment_allocations', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
