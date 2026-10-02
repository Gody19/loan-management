<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_report_schedule_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ai_report_schedule_id')->constrained('ai_report_schedules')->cascadeOnDelete();

            // The Phase 12.0 report this execution produced, when it produced one.
            $table->foreignId('ai_intelligence_report_id')->nullable()->constrained('ai_intelligence_reports')->nullOnDelete();

            // The idempotency key: schedule + resolved period. This unique index
            // is the concurrency guard — two concurrent executions of the same
            // schedule for the same period can never both claim a run, so a
            // schedule can never publish two reports, or notify twice, for one
            // window.
            $table->string('execution_key', 191);

            // scheduled | manual — a manual run is an immediate, audited
            // execution of the same path and never modifies the schedule.
            $table->string('trigger', 20)->default('scheduled');

            $table->string('status', 20)->default('running');

            // The resolved, completed reporting period this run reported on.
            $table->string('period_type', 30);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('timezone', 64);

            $table->unsignedInteger('notifications_sent')->default(0);
            $table->boolean('digest_available')->default(false);

            // Non-secret diagnostic for a failed or skipped execution.
            $table->text('failure_reason')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            // Only the first timestamp column in a table may be NOT NULL (see the
            // schedules migration); both are nullable and always set explicitly.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique('execution_key', 'ai_report_schedule_runs_execution_key_unique');
            $table->index(['ai_report_schedule_id', 'status'], 'ai_report_schedule_runs_schedule_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_report_schedule_runs');
    }
};
