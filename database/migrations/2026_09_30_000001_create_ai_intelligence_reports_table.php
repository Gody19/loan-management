<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_intelligence_reports', function (Blueprint $table) {
            $table->id();

            // Tenant scope. organization_id is always the acting user's own
            // organization (the browser never supplies one); branch_id is
            // nullable and, when set, must belong to the same organization.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('report_type', 60);
            $table->string('status', 30)->default('generating');

            // Reporting period (the historical window being reported on).
            $table->string('period_type', 30)->default('this_month');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('previous_period_start')->nullable();
            $table->date('previous_period_end')->nullable();

            // The newest authoritative record the report could observe. Kept
            // distinct from the period so a later generation can never be
            // implied to belong to the historical period.
            $table->timestamp('data_through');
            $table->timestamp('generated_at');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            // Structured, classified report dataset. Figures always come from
            // the deterministic reporting service, never from the AI model.
            $table->json('report_data');

            // Optional AI narrative over the sanitized report context.
            $table->json('narrative')->nullable();
            $table->boolean('ai_generated')->default(false);

            // Non-secret diagnostic for a failed generation (validated by the
            // service to a safe reason string).
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'report_type', 'generated_at'], 'ai_reports_org_type_generated_idx');
            $table->index(['status']);
            $table->index(['branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_intelligence_reports');
    }
};