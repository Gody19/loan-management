<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_application_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_approval_level_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('approval_level');
            $table->string('action', 20)->default('pending');
            $table->decimal('level_minimum_amount', 18, 2)->nullable();
            $table->decimal('level_maximum_amount', 18, 2)->nullable();
            $table->decimal('approved_amount', 18, 2)->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('loan_application_id');
            $table->index('approval_level');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_application_approvals');
    }
};
