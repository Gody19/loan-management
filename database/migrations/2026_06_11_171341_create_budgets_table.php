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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('limit_amount', 12, 2);
            $table->decimal('spent_amount', 12, 2)->default(0);
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->enum('period', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'completed', 'exceeded'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
