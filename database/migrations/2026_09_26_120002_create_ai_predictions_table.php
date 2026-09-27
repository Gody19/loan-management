<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('type', 40);
            $table->string('status', 30)->default('generated');
            $table->string('scope', 20)->default('organization');
            $table->string('method', 40);
            $table->string('model_version', 40);
            $table->string('target_period', 7);
            $table->date('data_through');
            $table->unsignedSmallInteger('horizon')->default(3);
            $table->string('confidence', 20);
            $table->string('data_quality', 20);
            $table->decimal('value_total', 18, 2)->nullable();
            $table->string('currency', 10)->default('TZS');
            $table->json('series')->nullable();
            $table->json('factors')->nullable();
            $table->json('assumptions')->nullable();
            $table->text('explanation')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');

            $table->timestamps();

            // Idempotency: one persisted insight per (organization, type,
            // method, data snapshot). Re-running the same snapshot updates the
            // same row instead of duplicating it.
            $table->unique(
                ['organization_id', 'type', 'method', 'data_through'],
                'ai_predictions_snapshot_unique',
            );
            $table->index(['organization_id', 'type', 'status', 'generated_at'], 'ai_predictions_status_idx');
            $table->index(['type']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_predictions');
    }
};
