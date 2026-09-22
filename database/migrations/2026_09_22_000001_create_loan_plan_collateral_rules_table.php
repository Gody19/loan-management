<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_plan_collateral_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('minimum_amount', 18, 2);
            $table->decimal('maximum_amount', 18, 2);
            $table->boolean('collateral_required')->default(false);
            $table->decimal('coverage_percentage', 8, 2)->default(100);
            $table->decimal('minimum_collateral_value', 18, 2)->default(0);
            $table->unsignedSmallInteger('minimum_assets')->default(1);
            $table->unsignedSmallInteger('maximum_assets')->default(1);
            $table->json('allowed_collateral_types')->nullable();
            $table->json('required_document_types')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('loan_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_plan_collateral_rules');
    }
};
