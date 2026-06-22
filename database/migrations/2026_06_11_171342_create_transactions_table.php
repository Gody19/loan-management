<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('portfolio_id')->nullable()->constrained('portfolios')->onDelete('set null');
            $table->unsignedBigInteger('budget_id')->nullable();
            $table->enum('type', ['income', 'expense', 'transfer', 'investment'])->default('expense');
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->date('transaction_date');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
