<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('interest_amount', 18, 2);
            $table->decimal('total_amount', 18, 2);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2);
            $table->decimal('running_balance', 18, 2);
            $table->string('status', 30)->default('pending');
            $table->date('paid_date')->nullable();
            $table->integer('days_overdue')->default(0);
            $table->decimal('late_fee', 18, 2)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['loan_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_schedules');
    }
};
