<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete()->after('reversed_transaction_id');
        });

        Schema::table('share_transactions', function (Blueprint $table) {
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete()->after('reversed_transaction_id');
        });

        Schema::table('welfare_transactions', function (Blueprint $table) {
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete()->after('reversed_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->dropForeign(['reversed_by']);
            $table->dropColumn('reversed_by');
        });

        Schema::table('share_transactions', function (Blueprint $table) {
            $table->dropForeign(['reversed_by']);
            $table->dropColumn('reversed_by');
        });

        Schema::table('welfare_transactions', function (Blueprint $table) {
            $table->dropForeign(['reversed_by']);
            $table->dropColumn('reversed_by');
        });
    }
};
