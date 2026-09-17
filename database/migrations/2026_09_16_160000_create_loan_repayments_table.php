<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('repayment_number', 30)->unique();
            $table->decimal('amount', 18, 2)->comment('Total amount received');
            $table->decimal('principal_portion', 18, 2)->default(0);
            $table->decimal('interest_portion', 18, 2)->default(0);
            $table->decimal('fee_portion', 18, 2)->default(0);
            $table->date('payment_date');
            $table->string('payment_method', 30)->default('cash');
            $table->string('reference_number', 100)->nullable();
            $table->string('status', 30)->default('posted');
            $table->string('reversal_reason', 500)->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['loan_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['payment_date']);
            $table->index(['repayment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
