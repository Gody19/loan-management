<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_learning_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_evaluation_id')->constrained('ai_evaluations')->cascadeOnDelete();
            $table->foreignId('ai_feedback_id')->nullable()->constrained('ai_feedback')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('dataset_version')->default(1);
            $table->string('status')->default('active')->index();
            $table->text('input_text');
            $table->text('original_response');
            $table->text('corrected_response')->nullable();
            $table->json('evaluation_metadata')->nullable();
            $table->json('sanitization_report')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('ai_learning_examples')->nullOnDelete();
            $table->timestamps();

            // Traceability plus duplicate-approval protection: an evaluation
            // can never produce two dataset examples.
            $table->unique('ai_evaluation_id');
            $table->index(['organization_id', 'status']);
            $table->index(['dataset_version', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learning_examples');
    }
};
