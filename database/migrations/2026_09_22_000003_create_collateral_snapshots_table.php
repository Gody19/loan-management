<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collateral_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_application_id');
            $table->unsignedBigInteger('loan_plan_id');
            $table->unsignedBigInteger('collateral_rule_id')->nullable();
            $table->decimal('requested_amount', 18, 2);
            $table->boolean('collateral_required')->default(false);
            $table->decimal('coverage_percentage', 8, 2)->default(100);
            $table->decimal('minimum_collateral_value', 18, 2)->default(0);
            $table->unsignedSmallInteger('minimum_assets')->default(0);
            $table->unsignedSmallInteger('maximum_assets')->default(0);
            $table->json('allowed_collateral_types')->nullable();
            $table->json('required_document_types')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('snapshot_created_at');
            $table->timestamps();

            $table->foreign('loan_application_id', 'cs_app_fk')->references('id')->on('loan_applications')->cascadeOnDelete();
            $table->foreign('loan_plan_id', 'cs_plan_fk')->references('id')->on('loan_plans')->restrictOnDelete();
            $table->foreign('collateral_rule_id', 'cs_rule_fk')->references('id')->on('loan_plan_collateral_rules')->nullOnDelete();

            $table->index('loan_application_id');
            $table->index('loan_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collateral_snapshots');
    }
};
