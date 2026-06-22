<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->default(0)->after('amount');
            $table->decimal('total_payable', 12, 2)->default(0)->after('interest_rate');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('total_payable');
            $table->decimal('repayment_amount', 12, 2)->default(0)->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'total_payable', 'amount_paid', 'repayment_amount']);
        });
    }
};
