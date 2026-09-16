<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_application_collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('collateral_type', 30);
            $table->text('description');
            $table->decimal('estimated_value', 18, 2);
            $table->string('reference_number', 100)->nullable();
            $table->text('ownership_details')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('loan_application_id');
            $table->index('collateral_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_application_collaterals');
    }
};
