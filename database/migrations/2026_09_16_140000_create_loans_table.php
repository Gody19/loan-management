<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('loan_number', 30)->unique();
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('disbursed_amount', 18, 2);
            $table->decimal('interest_rate', 8, 4)->comment('Annual interest rate');
            $table->string('interest_method', 30)->default('flat');
            $table->integer('term_months');
            $table->string('repayment_frequency', 30)->default('monthly');
            $table->decimal('total_interest', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('processing_fee', 18, 2)->default(0);
            $table->decimal('insurance_fee', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('outstanding_balance', 18, 2)->default(0);
            $table->integer('grace_period')->default(0)->comment('Days');
            $table->string('status', 30)->default('pending_disbursement');
            $table->date('disbursement_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('next_payment_date')->nullable();
            $table->integer('installments_paid')->default(0);
            $table->integer('total_installments');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
