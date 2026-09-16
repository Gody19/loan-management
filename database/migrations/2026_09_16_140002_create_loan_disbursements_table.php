<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('disbursement_number', 30)->unique();
            $table->decimal('amount', 18, 2);
            $table->decimal('processing_fee', 18, 2)->default(0);
            $table->decimal('insurance_fee', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->comment('Amount after deductions');
            $table->date('disbursement_date');
            $table->string('status', 30)->default('pending');
            $table->string('disbursement_method', 30)->default('cash');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['loan_id']);
            $table->index(['disbursement_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_disbursements');
    }
};
