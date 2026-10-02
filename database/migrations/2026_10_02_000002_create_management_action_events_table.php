<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_action_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('management_action_id')
                ->constrained('management_actions')
                ->cascadeOnDelete();

            // Denormalized tenant scope so the audit trail can be filtered even
            // if the parent action is removed.
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);

            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();

            // Deliberately not foreign keys: the history must survive the
            // deletion of the users it references.
            $table->unsignedBigInteger('old_assignee_id')->nullable();
            $table->unsignedBigInteger('new_assignee_id')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Append-only: only a creation timestamp, never an updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index(['management_action_id', 'id'], 'management_action_events_action_idx');
            $table->index('organization_id', 'management_action_events_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_action_events');
    }
};
