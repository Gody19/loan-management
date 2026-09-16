<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vicoba_group_id')->nullable()->constrained('vicoba_groups')->nullOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_plan_id')->constrained()->restrictOnDelete();

            $table->string('application_number', 30)->unique();
            $table->decimal('requested_amount', 18, 2);
            $table->integer('requested_term');
            $table->string('repayment_frequency', 30)->default('monthly');
            $table->string('loan_purpose', 30)->default('development');
            $table->text('purpose_description')->nullable();

            $table->date('application_date');
            $table->string('status', 30)->default('draft');

            // Eligibility snapshot
            $table->json('eligibility_snapshot')->nullable();
            $table->timestamp('eligibility_checked_at')->nullable();

            // Submission
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            // Rejection
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // Cancellation
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('organization_id');
            $table->index('branch_id');
            $table->index('member_id');
            $table->index('loan_plan_id');
            $table->index('status');
            $table->index('application_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
