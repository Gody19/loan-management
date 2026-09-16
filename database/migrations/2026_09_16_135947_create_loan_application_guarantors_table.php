<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_application_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guarantor_member_id')->constrained('members')->restrictOnDelete();
            $table->decimal('guaranteed_amount', 18, 2);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();

            // Confirmation
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            // Rejection
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unique constraint: no duplicate guarantors per application
            $table->unique(['loan_application_id', 'guarantor_member_id'], 'ln_app_guarantor_unique');

            // Indexes
            $table->index('loan_application_id');
            $table->index('guarantor_member_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_application_guarantors');
    }
};
