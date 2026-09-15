<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vicoba_group_id')->constrained()->restrictOnDelete();
            $table->string('transaction_number', 20)->unique();
            $table->string('transaction_type', 20);
            $table->integer('quantity');
            $table->decimal('share_price', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->integer('balance_shares_before');
            $table->integer('balance_shares_after');
            $table->decimal('balance_value_before', 18, 2);
            $table->decimal('balance_value_after', 18, 2);
            $table->date('transaction_date');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('completed');
            $table->unsignedBigInteger('reversed_transaction_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('share_account_id');
            $table->index('member_id');
            $table->index('transaction_number');
            $table->index('transaction_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_transactions');
    }
};
