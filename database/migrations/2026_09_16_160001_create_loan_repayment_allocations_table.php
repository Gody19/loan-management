<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_repayment_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_repayment_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            $table->decimal('amount', 18, 2)->comment('Amount allocated to this installment');
            $table->decimal('principal_allocation', 18, 2)->default(0);
            $table->decimal('interest_allocation', 18, 2)->default(0);
            $table->decimal('fee_allocation', 18, 2)->default(0);

            $table->timestamps();

            $table->index(['loan_repayment_id']);
            $table->index(['loan_id', 'loan_repayment_schedule_id'], 'lra_loan_schedule_idx');
            $table->index(['organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_allocations');
    }
};
