<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_application_collaterals', function (Blueprint $table) {
            $table->decimal('reviewed_value', 18, 2)->nullable()->after('estimated_value');
            $table->timestamp('valuation_date')->nullable()->after('reviewed_value');
            $table->string('valuation_reference', 100)->nullable()->after('valuation_date');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('updated_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->json('member_details')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('loan_application_collaterals', function (Blueprint $table) {
            $table->dropColumn([
                'reviewed_value', 'valuation_date', 'valuation_reference',
                'reviewed_by', 'reviewed_at', 'review_notes', 'member_details',
            ]);
        });
    }
};
