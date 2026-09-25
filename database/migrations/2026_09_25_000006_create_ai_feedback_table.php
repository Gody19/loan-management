<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_message_id')->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_model_version_id')->nullable()->constrained('ai_model_versions')->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('submitted')->index();
            $table->text('correction')->nullable();
            $table->string('reason')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();

            // One feedback record per user per AI message keeps a repeated
            // button click idempotent instead of multiplying rows.
            $table->unique(['ai_message_id', 'user_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};
