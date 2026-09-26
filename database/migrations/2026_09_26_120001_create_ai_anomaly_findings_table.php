<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_anomaly_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 60);
            $table->string('severity', 30);
            $table->string('title', 200);
            $table->text('description');
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 10)->default('TZS');
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->date('detection_date');
            $table->timestamp('detected_at');
            $table->string('status', 30)->default('detected');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'organization_id', 'type', 'source_type', 'source_id', 'detection_date',
            ], 'ai_anomaly_daily_unique');
            $table->index(['organization_id', 'status', 'detection_date']);
            $table->index(['type']);
            $table->index(['severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_anomaly_findings');
    }
};
