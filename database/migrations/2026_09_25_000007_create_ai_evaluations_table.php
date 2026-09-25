<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_feedback_id')->constrained('ai_feedback')->cascadeOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->json('scores')->nullable();
            $table->text('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // One human evaluation per feedback record; a second review attempt
            // updates the existing evaluation rather than forking history.
            $table->unique('ai_feedback_id');
            $table->index(['organization_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluations');
    }
};
