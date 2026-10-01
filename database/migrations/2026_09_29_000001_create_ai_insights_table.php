<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 60);
            $table->string('severity', 30);
            $table->string('title', 200);
            $table->text('summary');
            $table->text('recommendation')->nullable();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->nullableMorphs('object');
            $table->string('dedup_key', 300);
            $table->string('status', 30)->default('new');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at');
            $table->date('data_through')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            $table->unique(['dedup_key'], 'ai_insights_dedup_unique');
            $table->index(['organization_id', 'status', 'generated_at']);
            $table->index(['type']);
            $table->index(['severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
