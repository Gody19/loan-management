<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('loan_purpose', 30)->default('development');

            // Amount rules
            $table->decimal('minimum_amount', 18, 2)->default(0);
            $table->decimal('maximum_amount', 18, 2)->default(0);

            // Interest rules
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->string('interest_method', 30)->default('flat');

            // Term rules (in months)
            $table->integer('minimum_term')->default(1);
            $table->integer('maximum_term')->default(12);

            // Repayment
            $table->string('repayment_frequency', 30)->default('monthly');

            // Active loans limit
            $table->integer('maximum_active_loans')->default(1);

            // Guarantor rules
            $table->boolean('requires_guarantor')->default(false);
            $table->integer('minimum_guarantors')->default(0);

            // Collateral
            $table->boolean('requires_collateral')->default(false);

            // Savings requirement
            $table->decimal('minimum_savings_balance', 18, 2)->default(0);
            $table->decimal('savings_multiplier', 8, 2)->default(1);

            // Share requirement
            $table->decimal('share_multiplier', 8, 2)->default(1);

            // Loan-to-savings ratio
            $table->decimal('maximum_loan_to_savings_ratio', 8, 2)->default(0);

            // Grace period (days)
            $table->integer('grace_period')->default(0);

            // Fees
            $table->decimal('processing_fee', 18, 2)->default(0);
            $table->decimal('insurance_fee', 18, 2)->default(0);

            // Late payment
            $table->boolean('late_payment_allowed')->default(true);

            // Status
            $table->string('status', 20)->default('active');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Constraints
            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->index('status');
            $table->index('loan_purpose');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_plans');
    }
};
