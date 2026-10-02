<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_report_schedules', function (Blueprint $table) {
            $table->id();

            // Tenant scope. organization_id is always the acting user's own
            // organization (the browser never supplies one); branch_id is
            // nullable and, when set, must belong to the same organization.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 120);

            // The Phase 12.0 report type this schedule produces, and the closed
            // frequency vocabulary. There is no cron column: a schedule can only
            // be one of the controlled frequencies, so a user can never smuggle
            // an arbitrary expression into the platform scheduler.
            $table->string('report_type', 60);
            $table->string('frequency', 20);

            // When the schedule fires, in its own timezone.
            $table->time('run_time');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();

            // The effective timezone the period and the run time are resolved in.
            // Always stored explicitly so a later application-timezone change can
            // never silently move a schedule's reporting day.
            $table->string('timezone', 64);

            // Audience mode plus, for SpecificUsers only, the chosen user ids.
            // Delivery still re-verifies each recipient on every run.
            $table->string('recipient_mode', 30);
            $table->json('recipients')->nullable();
            $table->boolean('include_narrative')->default(false);

            $table->boolean('is_active')->default(true);

            // Only the first timestamp column in a table may be NOT NULL: MySQL
            // implicitly gives the first such column DEFAULT CURRENT_TIMESTAMP
            // ON UPDATE CURRENT_TIMESTAMP, which makes a second one invalid. Both
            // are therefore nullable and the service always sets them explicitly.
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['is_active', 'next_run_at'], 'ai_report_schedules_due_idx');
            $table->index(['organization_id', 'is_active'], 'ai_report_schedules_org_active_idx');
            $table->index(['branch_id']);

            // One identical schedule (same organization, branch, report type and
            // frequency) may not be registered twice, which would otherwise
            // produce duplicate reports and duplicate notifications.
            $table->unique(
                ['organization_id', 'branch_id', 'report_type', 'frequency'],
                'ai_report_schedules_unique_definition_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_report_schedules');
    }
};
