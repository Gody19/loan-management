<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_actions', function (Blueprint $table) {
            $table->id();

            // Tenant scope. organization_id is always the acting user's trusted
            // organization (never request supplied); branch_id is nullable and,
            // when set, must be a branch the acting user is authorized for. A
            // null branch means the follow-up is organization-wide.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // The person who created the action and the person currently
            // responsible for it. Both are nullable so deleting a user leaves
            // the workflow record intact (it is never cascaded away).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 160);
            $table->text('description')->nullable();

            // Closed vocabulary, always human chosen.
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('open');

            // Workflow dates. There is deliberately no money column: a
            // management action is a task, not a financial or accounting record.
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Source traceability. The action points at the intelligence
            // artifact it was raised from without copying it; the label is a
            // human-readable snapshot so the action stays readable even if the
            // source is later deleted. No foreign key: deleting intelligence
            // must never delete the human follow-up.
            $table->string('source_type', 191)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_label', 191)->nullable();

            $table->text('completion_notes')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // Bounded, indexed filters: open/overdue by org, "assigned to me",
            // and source lookups. The foreign keys already index their columns.
            $table->index(['organization_id', 'status', 'due_date'], 'management_actions_org_status_due_idx');
            $table->index(['organization_id', 'assigned_to', 'status'], 'management_actions_org_assignee_status_idx');
            $table->index(['source_type', 'source_id'], 'management_actions_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_actions');
    }
};
