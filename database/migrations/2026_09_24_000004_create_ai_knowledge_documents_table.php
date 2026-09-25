<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vicoba_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('document_type')->index();
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft')->index();
            $table->string('visibility')->default('organization')->index();
            $table->string('checksum')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_documents');
    }
};