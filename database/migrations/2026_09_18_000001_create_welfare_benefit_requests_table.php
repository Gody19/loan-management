<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welfare_benefit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('welfare_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vicoba_group_id')->constrained()->restrictOnDelete();
            $table->string('request_number', 20)->unique();
            $table->decimal('requested_amount', 18, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('welfare_transaction_id')->nullable()->nullOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welfare_benefit_requests');
    }
};
